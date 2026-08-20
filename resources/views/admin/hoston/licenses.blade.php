@extends('layouts.admin')

@section('title')
    Game Licenses
@endsection

@section('content-header')
    <h1>Game Licenses<small>Encrypted pools for commercially licensed titles (e.g. Farming Simulator 25).</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.hoston.index') }}">Infrastructure</a></li>
        <li class="active">Game Licenses</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">License Pools</h3>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <tbody>
                        <tr>
                            <th>Name</th>
                            <th>Game</th>
                            <th>Provider</th>
                            <th>Env Variable</th>
                            <th class="text-center">Licenses</th>
                            <th class="text-right">Actions</th>
                        </tr>
                        @foreach ($pools as $pool)
                            <tr>
                                <td><a href="{{ route('admin.hoston.licenses.view', $pool->id) }}">{{ $pool->name }}</a></td>
                                <td>{{ $pool->catalog?->name ?? '-' }}</td>
                                <td><span class="label label-primary">{{ $pool->provider }}</span></td>
                                <td><code>{{ $pool->license_variable }}</code></td>
                                <td class="text-center">{{ $pool->licenses_count }}</td>
                                <td class="text-right">
                                    <a href="{{ route('admin.hoston.licenses.view', $pool->id) }}" class="btn btn-xs btn-primary">Manage</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-xs-12 col-md-6">
        <div class="box box-success">
            <div class="box-header with-border">
                <h3 class="box-title">Add License Pool</h3>
            </div>
            <form action="{{ route('admin.hoston.licenses.store') }}" method="POST">
                @csrf
                <div class="box-body">
                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" name="name" class="form-control" required placeholder="Farming Simulator 25">
                    </div>
                    <div class="form-group">
                        <label>Slug</label>
                        <input type="text" name="slug" class="form-control" required placeholder="farming-simulator-25">
                    </div>
                    <div class="form-group">
                        <label>Game</label>
                        <select name="game_catalog_id" class="form-control">
                            <option value="">- None -</option>
                            @foreach ($catalog as $entry)
                                <option value="{{ $entry->id }}">{{ $entry->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Provider</label>
                        <input type="text" name="provider" class="form-control" placeholder="giants">
                    </div>
                    <div class="form-group">
                        <label>License Environment Variable</label>
                        <input type="text" name="license_variable" class="form-control" required value="GAME_LICENSE">
                        <p class="help-block">The egg environment variable that receives the license at provisioning time.</p>
                    </div>
                </div>
                <div class="box-footer">
                    <button class="btn btn-success">Create Pool</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
