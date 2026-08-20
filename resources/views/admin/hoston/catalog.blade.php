@extends('layouts.admin')

@section('title')
    Game Catalog
@endsection

@section('content-header')
    <h1>Game Catalog<small>Customer-facing games mapped to Pterodactyl Eggs and resource profiles.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.hoston.index') }}">Infrastructure</a></li>
        <li class="active">Game Catalog</li>
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
                            <th>Docker Image</th>
                            <th>Min RAM</th>
                            <th>Recommended RAM</th>
                            <th class="text-center">Enabled</th>
                        </tr>
                        @foreach ($catalog as $entry)
                            <tr>
                                <td>{{ $entry->name }}</td>
                                <td><code>{{ $entry->slug }}</code></td>
                                <td>{{ $entry->egg?->name ?? '-' }}</td>
                                <td><code>{{ $entry->default_image ?? '-' }}</code></td>
                                <td>{{ $entry->min_ram }} MB</td>
                                <td>{{ $entry->recommended_ram }} MB</td>
                                <td class="text-center">
                                    <span class="label label-{{ $entry->enabled ? 'success' : 'default' }}">{{ $entry->enabled ? 'Enabled' : 'Disabled' }}</span>
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
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Resource Profiles</h3>
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
                            <th class="text-center">Backups</th>
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
                                <td class="text-center">{{ $profile->backups }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
