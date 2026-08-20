@extends('layouts.admin')

@section('title')
    VM Templates
@endsection

@section('content-header')
    <h1>VM Templates<small>Cloud-Init enabled Proxmox templates used for provisioning.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.hoston.index') }}">Infrastructure</a></li>
        <li class="active">VM Templates</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Templates</h3>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <tbody>
                        <tr>
                            <th>Name</th>
                            <th>Provider</th>
                            <th>Cluster</th>
                            <th class="text-center">Template VMID</th>
                            <th>Storage</th>
                            <th>Bridge</th>
                            <th class="text-center">Cloud-Init</th>
                            <th class="text-center">Wings Bootstrap</th>
                        </tr>
                        @foreach ($templates as $template)
                            <tr>
                                <td>{{ $template->name }}</td>
                                <td>{{ $template->provider?->name ?? '-' }}</td>
                                <td>{{ $template->cluster?->name ?? '-' }}</td>
                                <td class="text-center"><code>{{ $template->template_vmid }}</code></td>
                                <td><code>{{ $template->storage }}</code></td>
                                <td><code>{{ $template->bridge }}</code></td>
                                <td class="text-center">
                                    <span class="label label-{{ $template->cloud_init_enabled ? 'success' : 'default' }}">{{ $template->cloud_init_enabled ? 'Enabled' : 'Disabled' }}</span>
                                </td>
                                <td class="text-center">
                                    <span class="label label-{{ $template->wings_bootstrap_enabled ? 'success' : 'default' }}">{{ $template->wings_bootstrap_enabled ? 'Enabled' : 'Disabled' }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
