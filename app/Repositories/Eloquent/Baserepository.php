<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\BaseRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

abstract class BaseRepository implements BaseRepositoryInterface
{
    public function __construct(protected Model $model) {}

    public function findById(int $id, array $relations = []): ?Model
    {
        return $this->model
            ->newQuery()
            ->with($relations)
            ->find($id);
    }

    public function findByIdOrFail(int $id, array $relations = []): Model
    {
        $model = $this->findById($id, $relations);

        if (! $model) {
            throw (new ModelNotFoundException())->setModel(
                get_class($this->model),
                $id
            );
        }

        return $model;
    }

    public function all(array $filters = [], array $relations = []): Collection
    {
        return $this->model
            ->newQuery()
            ->with($relations)
            ->get();
    }

    public function paginate(int $perPage = 15, array $filters = [], array $relations = []): LengthAwarePaginator
    {
        return $this->model
            ->newQuery()
            ->with($relations)
            ->paginate($perPage);
    }

    public function create(array $data): Model
    {
        return $this->model->newQuery()->create($data);
    }

    public function update(Model $model, array $data): Model
    {
        $model->update($data);
        return $model->refresh();
    }

    public function delete(Model $model): bool
    {
        return (bool) $model->delete();
    }
}
