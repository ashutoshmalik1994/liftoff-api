<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Services\EncryptionService;
use Exception;

class BaseApiController extends Controller
{
    protected $transactionId;
    protected $encryptionService;

    public function __construct(EncryptionService $encryptionService)
    {
        $this->transactionId = (string) Str::uuid();
        $this->encryptionService = $encryptionService;
    }

    protected function successResponse($data = [], $message = 'Success', $code = 200)
    {
        Log::info("Transaction {$this->transactionId} success", [
            'response' => $data
        ]);

        return response()->json([
            'transaction_id' => $this->transactionId,
            'status' => 'success',
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    protected function errorResponse($message = 'Something went wrong', $code = 500, $exception = null)
    {
        Log::error("Transaction {$this->transactionId} failed", [
            'error' => $message,
            'exception' => $exception?->getMessage()
        ]);

        return response()->json([
            'transaction_id' => $this->transactionId,
            'status' => 'error',
            'message' => $message,
        ], $code);
    }

    protected function encryptPayload($data)
    {
        return $this->encryptionService->encrypt(json_encode($data));
    }

    protected function decryptPayload($encryptedData)
    {
        $decrypted = $this->encryptionService->decrypt($encryptedData);
        return json_decode($decrypted, true);
    }
}
