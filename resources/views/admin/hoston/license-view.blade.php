@extends('layouts.admin')

@section('title')
    License Pool: {{ $pool->name }}
@endsection

@section('content-header')
    <h1>{{ $pool->name }}<small>Encrypted license keys. Never exposed to customers or the frontend.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.hoston.index') }}">Infrastructure</a></li>
        <li><a href="{{ route('admin.hoston.licenses') }}">Game Licenses</a></li>
        <li class="active">{{ $pool->name }}</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Licenses</h3>
                <div class="box-tools">
                    <button class="btn btn-sm btn-success" data-toggle="modal" data-target="#addLicenseModal">Add License</button>
                </div>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <tbody>
                        <tr>
                            <th>Key</th>
                            <th>Status</th>
                            <th>Assigned To</th>
                            <th>Allocated At</th>
                            <th class="text-right">Actions</th>
                        </tr>
                        @foreach ($licenses as $license)
                            <tr>
                                <td><code>{{ $license->masked }}</code></td>
                                <td>
                                    @php $cls = $license->status === 'available' ? 'success' : ($license->status === 'revoked' ? 'danger' : 'primary'); @endphp
                                    <span class="label label-{{ $cls }}">{{ $license->status }}</span>
                                </td>
                                <td>{{ $license->service?->name ?? '-' }}</td>
                                <td>{{ $license->allocated_at?->format('Y-m-d H:i') ?? '-' }}</td>
                                <td class="text-right">
                                    @if ($license->status !== 'revoked')
                                        <form action="{{ route('admin.hoston.licenses.revoke', [$pool->id, $license->id]) }}" method="POST" style="display:inline" onsubmit="return confirm('Revoke this license?');">
                                            @csrf
                                            <button class="btn btn-xs btn-danger">Revoke</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addLicenseModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form action="{{ route('admin.hoston.licenses.licenses', $pool->id) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    <h4 class="modal-title">Add License(s)</h4>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>License Key(s)</label>
                        <textarea name="license_keys" class="form-control" rows="5" placeholder="One license key per line"></textarea>
                        <p class="help-block">Keys are stored encrypted. Enter one per line for batch import.</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button class="btn btn-success">Add</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
