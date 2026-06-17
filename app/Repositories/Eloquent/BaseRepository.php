<?php

namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\BaseRepositoryInterface;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

abstract class BaseRepository implements BaseRepositoryInterface
{
    protected Model $modelInstance;

    public function __construct(Container $container)
    {
        $modelClass = $this->model();
        $this->modelInstance = $container->make($modelClass);
    }

    abstract public function model(): string;

    public function query(): Builder
    {
        return $this->modelInstance->newQuery();
    }

    public function find(int|string $id, array $with = []): ?Model
    {
        return $this->query()->with($with)->find($id);
    }

    public function findOrFail(int|string $id, array $with = []): Model
    {
        return $this->query()->with($with)->findOrFail($id);
    }

    public function all(array $with = []): Collection
    {
        return $this->query()->with($with)->get();
    }

    public function paginate(int $perPage = 15, array $with = [], array $filters = []): LengthAwarePaginator
    {
        $q = $this->query()->with($with);
        foreach ($filters as $col => $val) {
            if ($val === null || $val === '') continue;
            $q->where($col, $val);
        }
        return $q->latest()->paginate($perPage);
    }

    public function create(array $data): Model
    {
        return $this->modelInstance->newQuery()->create($data);
    }

    public function update(Model $model, array $data): Model
    {
        $model->update($data);
        return $model->fresh();
    }

    public function delete(Model $model): bool
    {
        return (bool) $model->delete();
    }

    public function forTenant(int $tenantId): Builder
    {
        return $this->query()->where('tenant_id', $tenantId);
    }
}
