# GitHub公開手順

この作品はソースをポートフォリオとして公開するために整理済み。GitHubへのアップロードはまだ行っていない。稼働アプリの外部公開は対象外。

## 公開対象

README.md、artisan、composer.json/ composer.lock/ app/ bootstrap/ config/ database/ resources/ public/ routes/ scripts/ tests/ docs/ docker/ Dockerfile/ compose.yaml/ phpunit.xml/ pint.json/ .github/ .env.example/ .gitignore等、今回のソース一式をリポジトリのルートへ置く。

.env、database.sqlite、storageの実行時ファイル、APP_KEY、vendor、個人端末の生ログは含めない。.env.exampleのAPP_KEYは空。phpunit.xmlの公開テストキーとSeederの固定パスワードは、実運用の秘密ではない。

## GitHub Desktopの場合

1. 新しい専用リポジトリを作る。推奨名 `invoiceflow-laravel-portfolio`。
2. 別のGitリポジトリの内側ではない保存先を選ぶ。このチャット全体の作業フォルダーを登録しない。
3. 今回のソースフォルダーの中身をコピーし、READMEがルートへ来ることを確認。
4. MacはCommand+Shift+.で隠しファイルを表示し、.github/.env.example/.gitignoreもコピーする。
5. Changesを確認してcommitする。
6. Publish repositoryで公開先を確認。一般公開する場合はKeep this code privateを外す。
7. 公開後にREADME画像・文書リンク・Actionsの結果を確認する。CI未実行の段階で成功バッジを付けない。

## Aboutの例

Description:
Laravel製の顧客・請求・入金管理ポートフォリオ。認証、所有分離、発行後変更禁止、過入金防止、入金の二重送信対策、実プロセス競合テストを実装。

Topics:
php laravel blade eloquent sqlite phpunit billing portfolio docker

Website:
稼働公開URLはないため空欄。

## ライセンス/公開後

作品固有の再利用条件は未設定。必要なら公開者がLICENSEを選択する。Laravel/依存のライセンスは別途確認する。本文はCHANGELOG.mdとdocs/PORTFOLIO.mdを使える。URLが確定したらGitHubプロフィールのPinned等へ掲載する。

[GitHub公式のリポジトリ作成手順](https://docs.github.com/en/repositories/creating-and-managing-repositories/creating-a-new-repository)
