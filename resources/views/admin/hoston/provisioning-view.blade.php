@extends('layouts.admin')

@section('title')
    Provisioning #{{ $job->external_id }}
@endsection

@section('content-header')
    <h1>Provisioning <small>#{{ $job->external_id }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.hoston.index') }}">Infrastructure</a></li>
        <li><a href="{{ route('admin.hoston.provisioning') }}">Provisioning Jobs</a></li>
        <li class="active">#{{ $job->external_id }}</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-xs-12 col-md-6">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Details</h3>
            </div>
            <div class="box-body">
                <dl class="dl-horizontal">
                    <dt>Customer</dt><dd>{{ $job->user?->email ?? '-' }}</dd>
                    <dt>Product</dt><dd>{{ $job->profile?->name ?? '-' }}</dd>
                    <dt>State</dt>
                    <dd>
                        @php $cls = match($job->status) { 'completed' => 'success', 'failed' => 'danger', 'running' => 'info', 'waiting_for_bootstrap' => 'info', default => 'warning' }; @endphp
                        <span class="label label-{{ $cls }}">{{ $job->status }}</span>
                    </dd>
                    <dt>Host</dt><dd>{{ $job->host?->name ?? '-' }}</dd>
                    <dt>VMID</dt><dd><code>{{ $job->vmid ?? '-' }}</code></dd>
                    <dt>Wings Node</dt><dd>{{ $job->wings_node_id ?? '-' }}</dd>
                    <dt>Game Server</dt><dd>{{ $job->server_id ?? '-' }}</dd>
                    <dt>Attempts</dt><dd>{{ $job->attempt_count }}</dd>
                </dl>
            </div>
            <div class="box-footer">
                @if (in_array($job->status, ['failed', 'waiting_for_bootstrap']))
                    <form action="{{ route('admin.hoston.provisioning.retry', $job->id) }}" method="POST" style="display:inline">
                        @csrf
                        <button class="btn btn-warning">Retry</button>
                    </form>
                @endif
                @if ($job->server_id)
                    <a class="btn btn-primary" href="/admin/servers/view/{{ $job->server_id }}">Open Server</a>
                @endif
            </div>
        </div>
    </div>

    <div class="col-xs-12 col-md-6">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Timeline</h3>
            </div>
            <div class="box-body">
                @if ($job->error)
                    <div class="alert alert-danger">{{ $job->error }}</div>
                @endif
                <ul class="timeline">
                    @foreach ($job->steps as $step)
                        @php
                            $icon = match($step->status) {
                                'success' => 'fa-check bg-green',
                                'failed' => 'fa-times bg-red',
                                'running' => 'fa-spinner bg-aqua',
                                'pending' => 'fa-circle-o bg-gray',
                                'skipped' => 'fa-minus bg-gray',
                                default => 'fa-circle-o bg-gray',
                            };
                        @endphp
                        <li>
                            <i class="fa {{ $icon }}"></i>
                            <div class="timeline-item">
                                <span class="time">{{ $step->finished_at ? $step->finished_at->format('H:i:s') : '-' }}</span>
                                <h3 class="timeline-header">
                                    {{ ucwords(str_replace('_', ' ', $step->step)) }}
                                    <span class="label label-{{ $step->status === 'success' ? 'success' : ($step->status === 'failed' ? 'danger' : 'default') }}">{{ $step->status }}</span>
                                </h3>
                                @if ($step->message)
                                    <div class="timeline-body">{{ $step->message }}</div>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
