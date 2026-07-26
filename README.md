# Daily English Log

## 概要
英語学習用の Web アプリです。（開発途中）
単語の登録・削除・検索・学習状態管理ができます。
Laravel API + Next.js のフルスタック構成で、`backend/`（API 専用）と `frontend/` を分離して開発・運用するモノレポです。

## 使用技術
### frontend
- Next.js 16.2.6（App Router）
- React 19.2.4
- TypeScript 5
- Tailwind CSS 4

### backend
- Laravel 13.8
- PHP 8.5.6
- MySQL 8.4（Laravel Sail / Docker）

### 認証
- Laravel Sanctum（SPA クッキーセッション方式）※追加中

## アーキテクチャ
- **モノレポ構成**: `backend/`（API 専用の Laravel）と `frontend/`（Next.js）を別々に起動する。共有ビルドは無い。
- **ドメインモデル**: `Category` hasMany `Word`（カテゴリーを削除すると、紐づく単語もカスケード削除される）。
- **学習状態の更新**: `is_learned` は専用エンドポイント `PATCH /api/words/{word}/toggle-learned` でのみ切り替える（マスアサインメント対象外）。
- **絞り込み**: 検索・学習状態・カテゴリーのフィルターはフロント側のインメモリで処理する。

## 主な機能
- 単語の追加・削除・検索
- 学習状態の管理
- カテゴリー管理（一覧・追加・編集・削除）
- ユーザー登録

## 開発の進捗
- [x] 単語の CRUD・検索・学習状態管理
- [x] カテゴリー管理（一覧・追加・編集・削除）
- [x] ユーザー登録（API + フォーム）
- [ ] 認証機能（ログイン）← 現在ここ
  - [x] Sanctum ステートフル設定 / CORS 設定
  - [x] ログイン API / ログアウト API
  - [ ] 認証必須ルートの保護・現在ユーザー取得（`/api/user`）
  - [ ] フロント連携（ログイン画面・ルート保護 middleware・ログアウト導線）

現状は、バックエンドのログイン / ログアウト API までが完成し、これから API のルート保護とフロント側の認証連携を進める段階です。

## 今後の拡張修正予定の機能
- user, word, categoriesのリレーション
- 学んだ単語でテストを実施
- 忘却曲線を考慮した復習機能
- 本番デプロイに向けたハードコードの修正
- モバイル用ネイティブアプリ

## 環境構築 / 起動方法
### 前提
- Docker（Laravel Sail 用）
- PHP 8.3+ / Composer
- Node.js（Next.js 16 対応版）

DB は Sail の MySQL 8.4（`compose.yaml`）を利用します。

### backend（`backend/` で実行）
```bash
cp .env.example .env              # 初回のみ
composer install
./vendor/bin/sail up -d           # app + MySQL を起動（既定ポート APP_PORT=80）
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
```
- Sail 運用では `.env` を MySQL に設定する（`DB_CONNECTION=mysql` / `DB_HOST=mysql`）。`.env.example` の既定は sqlite のため、Sail 利用時は書き換えが必要。
- API は `http://localhost/api` で提供される。

### frontend（`frontend/` で実行）
```bash
npm install
npm run dev                       # http://localhost:3000
```
- フロントは API の URL をハードコード（`http://localhost/api`）しているため、backend がポート 80 で動いている前提。

## 工夫した点
- Laravel API + Next.js の責務分離
- TypeScript による型安全性
- React state を用いたリアルタイムフィルター
- コンポーネント分割による保守性向上
