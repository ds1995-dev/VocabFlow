# 本番デプロイ手順（無料枠）

Daily English Log を無料枠のみで本番公開するための手順。

> **この文書の状態: 雛形**
> 手順は調査ベースで書いてあり、**実際のデプロイはまだ行っていない**。各サービスの UI や無料枠の条件は変わりやすいため、実施しながら「記録」欄と食い違った箇所を修正していくこと。
> 最終更新: 2026-08-28

---

## 構成

```
ブラウザ
  │
  ├─→ https://<vercel>.vercel.app        フロントエンド（Next.js / Vercel Hobby）
  │
  └─→ https://<render>.onrender.com/api  バックエンド（Laravel / Render Web Service）
                                              │
                                              └─→ TiDB Cloud Serverless（MySQL 8 互換）
```

| 層 | サービス | 選定理由 |
|---|---|---|
| フロント | Vercel Hobby | Next.js の標準的なホスト。設定は環境変数 1 つだけ |
| バックエンド | Render Web Service (Docker) | 無料枠で Docker が動く。PHP のネイティブランタイムが無いため Docker 前提 |
| DB | TiDB Cloud Serverless | MySQL 8 互換なのでローカル（Sail の MySQL 8.4）と差分ゼロ。無料枠が永続 |

**フロントとバックエンドが別ドメインでよい**のは、認証を Bearer トークン方式に統一してあるため（#130〜#132）。クッキー方式のままだと `*.vercel.app` と `*.onrender.com` がどちらも Public Suffix List に載っている都合で親ドメイン共有ができず、リバースプロキシか独自ドメインが必須だった。

## 前提

- `backend/Dockerfile` と `backend/docker/entrypoint.sh` がある（#136）
- アプリはステートレス。キュー・スケジューラ・永続ディスクのいずれも使わない
- GitHub リポジトリ `ds1995-dev/daily-english-log` が public

---

## 手順 1: DB を用意する（TiDB Cloud Serverless）

1. TiDB Cloud にサインアップし、Serverless クラスタを作成する（リージョンは Tokyo が近い）
2. 接続情報を控える。**パスワードは作成時にしか表示されないので必ず保存する**
3. データベースを作成する（例: `daily_english_log`）

控える値:

| 項目 | 用途 |
|---|---|
| Host | `DB_HOST` |
| Port | `DB_PORT`（TiDB は 4000） |
| User | `DB_USERNAME` |
| Password | `DB_PASSWORD` |
| Database | `DB_DATABASE` |

TiDB は TLS 必須だが、`backend/config/database.php` が既に `MYSQL_ATTR_SSL_CA` を読むようになっているため**コード変更は不要**。Debian ベースのイメージなので CA バンドルのパスは `/etc/ssl/certs/ca-certificates.crt`。

---

## 手順 2: バックエンドをデプロイする（Render）

1. Render で **New → Web Service** を選び、GitHub リポジトリを接続する
2. 設定:
   - **Runtime**: Docker
   - **Root Directory**: `backend`
   - **Dockerfile Path**: `backend/Dockerfile`
   - **Health Check Path**: `/up`（`backend/bootstrap/app.php` で登録済み）
   - **Instance Type**: Free
3. `APP_KEY` を生成する。ローカルで:
   ```bash
   cd backend && ./vendor/bin/sail artisan key:generate --show
   ```
   出力（`base64:...`）をそのまま環境変数に入れる。**ローカルの `.env` の値を使い回さない**。
4. 環境変数を設定する:

   | Key | Value | 備考 |
   |---|---|---|
   | `APP_ENV` | `production` | |
   | `APP_DEBUG` | `false` | **true のまま公開しない** |
   | `APP_KEY` | `base64:...` | 手順 3 で生成した値 |
   | `APP_URL` | `https://<render>.onrender.com` | |
   | `DB_CONNECTION` | `mysql` | |
   | `DB_HOST` / `DB_PORT` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | 手順 1 の値 | |
   | `MYSQL_ATTR_SSL_CA` | `/etc/ssl/certs/ca-certificates.crt` | TiDB の TLS 用 |
   | `SANCTUM_EXPIRATION` | `43200` | トークン有効期限（分）= 30 日 |
   | `FRONTEND_URL` | `https://<vercel>.vercel.app` | CORS の許可オリジン |
   | `LOG_CHANNEL` | `stderr` | Render のログに出すため |

   `FRONTEND_URL` は手順 3 が終わるまで確定しないので、**先に仮の値で作り、Vercel の URL が出てから更新して再デプロイする**。

5. デプロイし、ログで以下を確認する:
   ```
   INFO  Configuration cached successfully.
   INFO  Routes cached successfully.
   INFO  Migration table created successfully.  （初回）
   [mpm_prefork:notice] AH00163: Apache/... configured -- resuming normal operations
   ```
6. `curl https://<render>.onrender.com/up` が 200 を返すことを確認する

> `entrypoint.sh` が起動のたびに `migrate --force` を実行する。無料枠はインスタンスが 1 つなので競合しない。

---

## 手順 3: フロントエンドをデプロイする（Vercel）

1. Vercel で GitHub リポジトリをインポートする
2. **Root Directory** を `frontend` に設定する
3. 環境変数を設定する:

   | Key | Value |
   |---|---|
   | `NEXT_PUBLIC_API_BASE` | `https://<render>.onrender.com` |

   **末尾にスラッシュを付けない**（`src/lib/api.ts` が `${API_BASE}${path}` で連結するため）。
4. デプロイし、URL を控える
5. 手順 2 に戻って Render の `FRONTEND_URL` をこの URL に更新し、再デプロイする

---

## 手順 4: 疎通確認

ブラウザで以下を順に通す。

- [ ] 未ログインで `/` にアクセスすると `/login` にリダイレクトされる
- [ ] 新規登録できる（`POST /api/register` が 201）
- [ ] 登録後ダッシュボードが表示され、単語一覧・カテゴリ・ストリークが取得できる
- [ ] リロードしてもログイン状態が維持される
- [ ] 単語を登録・編集・削除できる
- [ ] カテゴリを登録・編集・削除できる
- [ ] クイズを出題・回答できる
- [ ] ログアウトすると `/login` に戻り、戻るボタンで保護ページに戻れない
- [ ] DevTools の Network で `/sanctum/csrf-cookie` へのリクエストが**発生していない**
- [ ] 各 API リクエストに `Authorization: Bearer ...` が付いている
- [ ] **Safari でも同じ流れが通る**
- [ ] スマートフォンの実機で表示が崩れていない

---

## 既知の制約

- **Render 無料枠は 15 分アクセスが無いとスリープする。** 次のアクセスで 30〜60 秒のコールドスタートが発生する。人に見せる直前に一度叩いて温めておく。
- **Vercel Hobby は非商用限定。** 収益化する場合は規約違反になるため Pro への移行が要る。
- **Vercel のプレビューデプロイからは API を叩けない。** `backend/config/cors.php` が `FRONTEND_URL` 単一オリジンしか許可しないため。プレビューも使うなら `allowed_origins_patterns` の追加が必要。
- **無料枠の条件は頻繁に変わる。** 着手時点で各サービスの現行プランを確認すること。
- **期限切れトークンが `personal_access_tokens` に残り続ける。** `sanctum:prune-expired` を回すスケジューラが無いため。件数が増えて問題になったら対処する。

---

## トラブルシューティング

| 症状 | 疑うところ |
|---|---|
| Render のデプロイが起動直後に落ちる | `APP_KEY` 未設定。ログに `No application encryption key has been specified.` が出る |
| 500 が返るが内容が分からない | `APP_DEBUG=true` に**一時的に**して原因を特定し、必ず `false` に戻す |
| DB に繋がらない | `MYSQL_ATTR_SSL_CA` のパス、TiDB 側の IP 許可設定 |
| フロントから API が CORS で弾かれる | Render の `FRONTEND_URL` が Vercel の URL と一致しているか。更新後に再デプロイしたか |
| ログインできるがリロードで落ちる | `NEXT_PUBLIC_API_BASE` の末尾スラッシュ。トークン cookie の `Secure` 属性（https でのみ付与される） |
| ヘルスチェックが通らない | Health Check Path が `/up` になっているか、`$PORT` を Apache が拾えているか |

---

## 記録

実際にデプロイしたら以下を埋める。

| 項目 | 値 |
|---|---|
| フロントエンド URL | （未デプロイ） |
| バックエンド URL | （未デプロイ） |
| DB サービス / リージョン | （未作成） |
| 初回デプロイ日 | － |
| 手順書との相違点 | － |
