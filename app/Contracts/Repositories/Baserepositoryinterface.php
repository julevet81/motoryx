<?php

namespace App\Contracts\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

interface BaseRepositoryInterface
{
    public function findById(int $id, array $relations = []): ?Model;

    public function findByIdOrFail(int $id, array $relations = []): Model;

    public function all(array $filters = [], array $relations = []): Collection;

    public function paginate(int $perPage = 15, array $filters = [], array $relations = []): LengthAwarePaginator;

    public function create(array $data): Model;

    public function update(Model $model, array $data): Model;

    public function delete(Model $model): bool;
}
