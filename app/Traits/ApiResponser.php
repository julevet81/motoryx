<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

trait ApiResponser
{
    protected function successResponse(mixed $data, string $message = 'Success', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $status);
    }

    protected function resourceResponse(JsonResource|ResourceCollection $resource, string $message = 'Success', int $status = 200): JsonResponse
    {
        return $resource->additional([
            'success' => true,
            'message' => $message,
        ])->response()->setStatusCode($status);
    }

    protected function paginatedResponse(LengthAwarePaginator $paginator, callable $transformer, string $message = 'Success'): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $paginator->getCollection()->map($transformer),
            'meta'    => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'from'         => $paginator->firstItem(),
                'to'           => $paginator->lastItem(),
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last'  => $paginator->url($paginator->lastPage()),
                'prev'  => $paginator->previousPageUrl(),
                'next'  => $paginator->nextPageUrl(),
            ],
        ]);
    }

    protected function errorResponse(string|array $message, int $status = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => is_array($message) ? 'Validation failed.' : $message,
            'errors'  => is_array($message) ? $message : null,
        ], $status);
    }

    protected function createdResponse(mixed $data, string $message = 'Created successfully.'): JsonResponse
    {
        return $this->successResponse($data, $message, 201);
    }

    protected function noContentResponse(): JsonResponse
    {
        return response()->json(['success' => true, 'message' => 'Deleted successfully.'], 200);
    }
}
