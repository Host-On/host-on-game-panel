@extends('layouts.admin')

@section('title')
    Games & Products
@endsection

@section('content-header')
    <h1>Games & Products<small>Customer-facing games mapped to Pterodactyl Eggs and resource profiles.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.hoston.index') }}">Infrastructure</a></li>
        <li class="active">Games & Products</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Games</h3>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <tbody>
                        <tr>
                            <th>Game</th>
                            <th>Slug</th>
                            <th>Egg</th>
                            <th>Runtime</th>
                            <th>Docker Image</th>
                            <th>License</th>
                            <th class="text-center">Enabled</th>
                            <th class="text-right">Actions</th>
                        </tr>
                        @foreach ($catalog as $entry)
                            <tr>
                                <td>{{ $entry->name }}</td>
                                <td><code>{{ $entry->slug }}</code></td>
                                <td>{{ $entry->egg?->name ?? '-' }}</td>
                                <td><span class="label label-{{ $entry->runtime === 'linux' ? 'default' : 'warning' }}">{{ $entry->runtime }}</span></td>
                                <td><code>{{ $entry->default_image ?? '-' }}</code></td>
                                <td>{{ $entry->requires_license ? '<span class="label label-primary">' . $entry->license_variable . '</span>' : '-' }}</td>
                                <td class="text-center">
                                    <span class="label label-{{ $entry->enabled ? 'success' : 'default' }}">{{ $entry->enabled ? 'Enabled' : 'Disabled' }}</span>
                                </td>
                                <td class="text-right">
                                    <form action="{{ route('admin.hoston.catalog.delete', $entry->id) }}" method="POST" style="display:inline" onsubmit="return confirm('Remove this game from the catalog?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-xs btn-danger">Delete</button>
                                    </form>
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
                <h3 class="box-title">Add Game</h3>
            </div>
            <form action="{{ route('admin.hoston.catalog') }}" method="POST">
                @csrf
                <div class="box-body">
                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" name="name" class="form-control" required placeholder="Minecraft">
                    </div>
                    <div class="form-group">
                        <label>Slug</label>
                        <input type="text" name="slug" class="form-control" required placeholder="minecraft">
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <input type="text" name="description" class="form-control" placeholder="Minecraft game server.">
                    </div>
                    <div class="form-group">
                        <label>Egg</label>
                        <select name="egg_id" class="form-control">
                            <option value="">- None -</option>
                            @foreach ($nests as $nest)
                                <optgroup label="{{ $nest->name }}">
                                    @foreach ($nest->eggs as $egg)
                                        <option value="{{ $egg->id }}">{{ $egg->name }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Runtime</label>
                        <select name="runtime" class="form-control">
                            <option value="linux">Linux (native)</option>
                            <option value="wine">Wine (Windows-only titles)</option>
                            <option value="proton">Proton</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Docker Image (optional)</label>
                        <input type="text" name="default_image" class="form-control" placeholder="ghcr.io/parkervcp/yolks:wine_latest">
                    </div>
                    <div class="checkbox">
                        <label><input type="checkbox" name="requires_license"> Requires a commercial game license</label>
                    </div>
                    <div class="form-group">
                        <label>License Environment Variable</label>
                        <input type="text" name="license_variable" class="form-control" placeholder="GAME_LICENSE">
                    </div>
                    <div class="row">
                        <div class="col-xs-6">
                            <div class="form-group">
                                <label>Min RAM (MB)</label>
                                <input type="number" name="min_ram" class="form-control" value="1024">
                            </div>
                        </div>
                        <div class="col-xs-6">
                            <div class="form-group">
                                <label>Recommended RAM (MB)</label>
                                <input type="number" name="recommended_ram" class="form-control" value="4096">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Ports</label>
                        <input type="text" name="ports" class="form-control" placeholder="25565/tcp">
                    </div>
                </div>
                <div class="box-footer">
                    <button class="btn btn-success">Add Game</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Resource Profiles (Products)</h3>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <tbody>
                        <tr>
                            <th>Product</th>
                            <th>Game</th>
                            <th class="text-center">vCPU</th>
                            <th class="text-center">VM RAM</th>
                            <th class="text-center">Game RAM</th>
                            <th class="text-center">Disk</th>
                            <th>Infrastructure</th>
                            <th>Template</th>
                            <th class="text-right">Actions</th>
                        </tr>
                        @foreach ($profiles as $profile)
                            <tr>
                                <td>{{ $profile->name }} <small>(<code>{{ $profile->slug }}</code>)</small></td>
                                <td>{{ $profile->catalog?->name ?? '-' }}</td>
                                <td class="text-center">{{ $profile->cpu }}</td>
                                <td class="text-center">{{ number_format($profile->memory / 1024, 0) }} GB</td>
                                <td class="text-center">{{ number_format($profile->game_memory / 1024, 1) }} GB</td>
                                <td class="text-center">{{ $profile->disk }} GB</td>
                                <td><span class="label label-primary">{{ $profile->infrastructure_type }}</span></td>
                                <td>{{ $profile->template?->name ?? '-' }}</td>
                                <td class="text-right">
                                    <form action="{{ route('admin.hoston.profiles.delete', $profile->id) }}" method="POST" style="display:inline" onsubmit="return confirm('Delete this product?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-xs btn-danger">Delete</button>
                                    </form>
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
                <h3 class="box-title">Add Resource Profile</h3>
            </div>
            <form action="{{ route('admin.hoston.profiles') }}" method="POST">
                @csrf
                <div class="box-body">
                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" name="name" class="form-control" required placeholder="Minecraft Performance">
                    </div>
                    <div class="form-group">
                        <label>Slug</label>
                        <input type="text" name="slug" class="form-control" required placeholder="minecraft-performance">
                    </div>
                    <div class="form-group">
                        <label>Game</label>
                        <select name="game_catalog_id" class="form-control" required>
                            <option value="">- Select -</option>
                            @foreach ($catalog as $entry)
                                <option value="{{ $entry->id }}">{{ $entry->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-xs-4">
                            <div class="form-group">
                                <label>vCPU</label>
                                <input type="number" name="cpu" class="form-control" required min="1" value="6">
                            </div>
                        </div>
                        <div class="col-xs-4">
                            <div class="form-group">
                                <label>VM RAM (GB)</label>
                                <input type="number" name="memory_gb" class="form-control" required min="1" value="16">
                            </div>
                        </div>
                        <div class="col-xs-4">
                            <div class="form-group">
                                <label>VM Disk (GB)</label>
                                <input type="number" name="disk_gb" class="form-control" required min="1" value="100">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-xs-4">
                            <div class="form-group">
                                <label>Game CPU</label>
                                <input type="number" name="game_cpu" class="form-control" required min="1" value="6">
                            </div>
                        </div>
                        <div class="col-xs-4">
                            <div class="form-group">
                                <label>Game RAM (GB)</label>
                                <input type="number" name="game_memory_gb" class="form-control" required min="1" value="14">
                            </div>
                        </div>
                        <div class="col-xs-4">
                            <div class="form-group">
                                <label>Game Disk (GB)</label>
                                <input type="number" name="game_disk_gb" class="form-control" required min="1" value="90">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Infrastructure Type</label>
                        <select name="infrastructure_type" class="form-control">
                            <option value="dedicated_vm">Dedicated VM</option>
                            <option value="shared">Shared</option>
                            <option value="static">Static Node</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>VM Template</label>
                        <select name="template_id" class="form-control">
                            <option value="">- None -</option>
                            @foreach ($templates as $template)
                                <option value="{{ $template->id }}">{{ $template->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Backups</label>
                        <input type="number" name="backups" class="form-control" value="3">
                    </div>
                </div>
                <div class="box-footer">
                    <button class="btn btn-success">Add Product</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
