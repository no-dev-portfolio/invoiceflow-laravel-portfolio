<?php
return [
 'required'=>':attributeを入力してください。','string'=>':attributeは文字列で入力してください。','email'=>':attributeは有効なメールアドレスで入力してください。','integer'=>':attributeは整数で入力してください。','array'=>':attributeの形式が不正です。','date_format'=>':attributeはYYYY-MM-DDで入力してください。','uuid'=>':attributeの形式が不正です。','regex'=>':attributeの形式が不正です。','in'=>':attributeの選択が不正です。','exists'=>':attributeが見つかりません。',
 'min'=>['numeric'=>':attributeは:min以上にしてください。','array'=>':attributeは:min件以上で入力してください。','string'=>':attributeは:min文字以上で入力してください。'],
 'max'=>['numeric'=>':attributeは:max以下にしてください。','array'=>':attributeは:max件以内で入力してください。','string'=>':attributeは:max文字以内で入力してください。'],
 'before_or_equal'=>':attributeは:date以前にしてください。','after_or_equal'=>':attributeは:date以降にしてください。',
 'attributes'=>['name'=>'会社名','contact_name'=>'担当者名','email'=>'メールアドレス','password'=>'パスワード','q'=>'検索語','status'=>'状況'],
];
