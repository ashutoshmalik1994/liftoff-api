<?php

namespace App\Http\Responses;

class ApiResponse
{
    public static function success($data = null, string $message = "Success", int $status = 200)
    {
        return response()->json([
            'status'  => $status,
            'message' => $message,
            'data'    => $data,
        ], $status);
    }

    public static function error(string $message = "Error", int $status = 400, $data = null)
    {
        return response()->json([
            'status'  => $status,
            'message' => $message,
            'data'    => $data,
        ], $status);
    }
}
