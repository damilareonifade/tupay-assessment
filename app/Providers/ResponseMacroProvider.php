<?php

namespace App\Providers;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\ServiceProvider;

class ResponseMacroProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Response::macro('v1', function ($statusCode = 200, $status_message = 'Request Succesful', $data = []) {
            return Response::json([
                'has_error' => false,
                'requestTime' => Carbon::now(),
                'status_code' => $statusCode,
                'message' => $status_message, // success message
                'data' => $data,
            ], $statusCode);
        });
        Response::macro('error', function ($message, $statusCode = 422, $data = []) {
            return Response::json([
                'has_error' => true,
                'requestTime' => Carbon::now(),
                'status_code' => $statusCode,
                'message' => $message, // error xyz has occured
                'data' => $data, // null or optional error payload
            ], $statusCode);
        });
        Response::macro('resource', function ($resource, $message = 'Request successful', $statusCode = 200) {
            $resource = (array) $resource;
            $data = $resource['resource'];

            return Response::json([
                'has_error' => false,
                'status_code' => $statusCode,
                'message' => $message, // success message
                'data' => $data, // null or application-specific data would go here
            ], $statusCode);
        });
        Response::macro('v2', function ($responseCode = '00', $responseMessage = '', $responseBody = [], $meta = false, $extra = [], $grouped_by_date = false, $date_field = null) {

            $response = [
                'hasError' => false,
                'statusCode' => 200,
                'requestTime' => Carbon::now(),
                'responseMessage' => empty($responseMessage) ? 'Request processed successfully' : $responseMessage,
                'data' => $responseBody,
            ];

            $resource = (array) $responseBody;
            if (isset($resource['resource'])) {
                $data = $resource['resource'];

                if ($meta) {
                    $meta = [
                        'currentPage' => $data->currentPage(),
                        'firstPageUrl' => $data->url(1),
                        'from' => $data->firstItem(),
                        'lastPage' => $data->lastPage(),
                        'lastPageUrl' => $data->url($data->lastPage()),
                        'nextPageUrl' => $data->nextPageUrl(),
                        'path' => request()->url(),
                        'perPage' => $data->perPage(),
                        'prevPageUrl' => $data->previousPageUrl(),
                        'to' => $data->lastItem(),
                        'total' => $data->total(),
                    ];

                    $response['meta'] = $meta;

                }

                if ($grouped_by_date) {
                    if ($date_field == null) {
                        $returndata = $data->groupBy(function ($item) {
                            return $item->created_at->format('Y-m-d');
                        })
                            ->sortByDesc(function ($group, $key) {
                                return $group->first()->created_at;
                            });
                    } else {
                        $returndata = $data->groupBy(function ($item) use ($date_field) {
                            // $item_array=(Array)$item;
                            if (isset($item[$date_field])) {
                                return $item[$date_field]->format('Y-m-d');

                            } else {
                                today()->format('Y-m-d');
                            }

                        })
                            ->sortByDesc(function ($group, $key) {
                                return $group->first()->id;
                            });
                    }

                    $response['data']['responseBody'] = $returndata;
                }
            }

            if (count($extra) > 0) {
                $response['extra'] = $extra;
            }

            return Response::json($response);
        });
    }
}
