Exment Ver.6系の最新バージョンに exceedone/exment の branch から

公式バージョンでは採用されなかったが、個人的に欲しいと思ったニッチな機能や改修内容を勝手に marge しています。

- feature/issue_9002 （検索フィルタで条件を保持したままにする）
- feature/add_expansion_view （1:Nリレーションのテーブルに親子ビューの追加）※ lang/enに不足項目追加済
- hotfixfeature/issue_1627（集計ビュー並び順設定の不具合解消）
- feature/issue_606_ex（パラメータ値を使用した表示専用で検索不可の項目をカスタム列に追加）
- feature/issue_1620（集計ビューでもフィルタ機能を使用可能に）

一応、エラーが出ないかどうかを動作確認していますが、素人がmargeしていますので

PHPStan等のチェックには引っかかると思いますし、予期せぬエラーが発生することがあります。

その場合、公式のissueにbug報告しても対応してはもらえませんので、ご注意ください。

以下、リンク

- **[公式マニュアル](https://exment.net/docs/#/ja/)**

- **[製品プラグイン](https://github.com/exment-git/plugin-product/tree/main/document/PluginInvoiceDocument)**  

- **[プラグインサンプル集](https://github.com/exment-git/plugin-sample)**  
