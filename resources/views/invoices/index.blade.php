@extends('layouts.app')
@section('title','請求書')
@section('eyebrow','INVOICES')
@section('subtitle','発行状況・入金状況を一覧で確認。')
@section('action')<a class="button" href="{{ route('invoices.create') }}">＋ 請求書を作成</a>@endsection
@section('content')<section class="panel"><form method="get" class="filters" action="{{ route('invoices.index') }}"><label>検索<input name="q" value="{{ $filters['q']??'' }}" placeholder="請求番号・件名・顧客名" maxlength="80"></label><label>状況<select name="status"><option value="">すべて</option>@foreach(['draft'=>'下書き','issued'=>'請求中','paid'=>'入金済','void'=>'取消'] as $value=>$label)<option value="{{ $value }}" @selected(($filters['status']??'')===$value)>{{ $label }}</option>@endforeach</select></label><button class="button secondary" type="submit">絞り込む</button><a href="{{ route('invoices.index') }}">解除</a></form>@include('invoices.table')<div class="pagination">{{ $invoices->links('pagination') }}</div></section>@endsection
