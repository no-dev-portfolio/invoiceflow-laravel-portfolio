@extends('layouts.app')
@section('title','ダッシュボード')
@section('action')<a class="button" href="{{ route('invoices.create') }}">＋ 請求書を作成</a>@endsection
@section('content')
<section class="metrics" aria-label="請求と入金の集計"><div class="metric"><span>未入金残高</span><strong>¥{{ number_format($openAmount) }}</strong><small>請求中 {{ $openCount }}件</small></div><div class="metric"><span>入金記録の累計</span><strong>¥{{ number_format($paidAmount) }}</strong><small>一部入金を含む記録済み金額</small></div><div class="metric warning"><span>支払期限を過ぎた残高</span><strong>¥{{ number_format($overdueAmount) }}</strong><small>本日 {{ today()->format('Y/m/d') }} 時点</small></div><div class="metric"><span>登録顧客</span><strong>{{ $customerCount }}<em>社</em></strong><small>このワークスペースの顧客</small></div></section>
<section class="panel"><div class="panel-heading"><div><h2>最近の請求書</h2><p>状況と残額を確認して、次のアクションへ。</p></div><a href="{{ route('invoices.index') }}">すべて見る →</a></div>@include('invoices.table',['invoices'=>$recent])</section>
<section class="workflow"><div><span class="step">01</span><h3>内容を準備</h3><p>顧客と明細を選び、下書きで確認。</p></div><div><span class="step">02</span><h3>請求書を確定</h3><p>発行後は明細を固定し、変更を防止。</p></div><div><span class="step">03</span><h3>入金を記録</h3><p>残額を照合し、完済を自動で判定。</p></div></section>
@endsection
