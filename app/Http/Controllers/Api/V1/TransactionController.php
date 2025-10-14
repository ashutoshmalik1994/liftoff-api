<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Transaction;
use App\Models\Receivable;
use App\Models\PayeeBank;
use App\Models\Payee;
use App\Models\Merchant;
use App\Models\BankAccount;
use App\Models\Payee_Info;
use App\Models\PayeeInternationalBank;
use App\Models\RecurringInformation;
use Illuminate\Support\Facades\Validator;
use App\Services\EncryptionService;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Events\TransactionCancelled;
use App\Events\ReceivableCancelled;
use App\Jobs\ProcessRecurringPaymentJob;
use App\Jobs\VerifyAndCreateBankAccount;
use Carbon\Carbon;
use App\Http\Responses\ApiResponse;

class TransactionController extends BaseApiController
{
    /**
     * @OA\Get(
     *     path="/api/v1/transactions",
     *     tags={"Transactions"},
     *     summary="List all sent transactions",
     *     description="Retrieve a list of sent transactions. You can filter results using either `payee_id`.",
     *     operationId="ee044a6172f37778765cd0f4b9dbc874",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="payee_id",
     *         in="query",
     *         required=false,
     *         description="Filter transactions by Payee (Merchant) ID",
     *          @OA\Schema(
     *              oneOf={
     *                  @OA\Schema(type="integer", example=22),
     *                  @OA\Schema(type="string", example="22A")
     *              }
     *          )
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         description="Page number for pagination",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         description="Number of records per page (default: 10)",
     *         @OA\Schema(type="integer", example=10)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="A list of transactions",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(
     *                     type="object",
     *                     @OA\Property(
     *                         property="status",
     *                         type="integer",
     *                         example=200
     *                     ),
     *                     @OA\Property(
     *                         property="message",
     *                         type="string",
     *                         example="Transactions fetched successfully"
     *                     )
     *                 ),
     *                 @OA\Schema(ref="#/components/schemas/TransactionListResponse")
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(ref="#/components/schemas/Unauthorized")
     *     )
     * )
     */

    public function getAllTransactions(Request $request)
    {
        $transactionId = (string) Str::uuid(); // Unique transaction ID

        try {
            $userId = Auth::id();
            $user = Auth::user(); // Logged in user details

            Log::channel("api")->info("Fetching transactions for user", [
                "transaction_id" => $transactionId,
                "user_id" => $userId,
            ]);

            // 🔹 Base Query
            $query = Transaction::where("is_deleted", false)
                ->where("user_id", $userId)
                ->with("payee:id,payee_name");

            // 🔹 Validate filters (Only one allowed)
            if ($request->has("payee_id") && $request->has("recurring_id")) {
                return ApiResponse::error(
                    "You can filter either by Payee ID or Recurring Payment ID, not both together.",
                    400,
                    ["transaction_id" => $transactionId]
                );
            }

            // 🔹 Apply Filters
            if ($request->has("payee_id")) {
                $query->where("payee_id", $request->payee_id);
            }

            if ($request->has("recurring_payment_id")) {
                $query->where("confirmation", $request->recurring_payment_id)
                    ->where("transfer_mode", "Recurring");
            }

            // 🔹 Pagination setup
            $perPage = (int) $request->get("per_page", 10);
            $page = (int) $request->get("page", 1);

            $paginator = $query->paginate($perPage, ["*"], "page", $page);

            // 🔹 Fetch Transactions
            $transactions = $query->get()->map(function ($item) {
                $item->status_text = $this->getStatusText($item->status);
                $item->{'Merchant/PayeeName'} = $item->payee
                    ? $item->payee->payee_name
                    : null;

                // 🔹 Memo split
                if (isset($item->memo)) {
                    $parts = explode('-', $item->memo, 2);
                    $item->{'Schedule Name'} = trim($parts[0] ?? '');
                    $item->{'Schedule Purpose'} = trim($parts[1] ?? '');
                    unset($item->memo);
                }

                // 🔹 RTN
                $item->return_date = $item->rtn_date
                    ? \Carbon\Carbon::parse($item->rtn_date)->format("m-d-Y")
                    : null;
                unset($item->rtn_date);

                // 🔹 Deposit Date
                if (empty($item->rtn_code)) {
                    if ($item->transfer_mode === "Real Time Payment" && $item->created_at) {
                        $item->deposit_date = $item->created_at
                            ? date("m-d-Y", strtotime($item->created_at))
                            : null;
                    } else {
                        $item->deposit_date = $item->created_at
                            ? date("m-d-Y", strtotime($item->created_at . " +3 day"))
                            : null;
                    }
                } else {
                    $item->deposit_date = null;
                }

                // 🔹 Dates
                $item->organization_date = $item->created_at
                    ? $item->created_at->format("m-d-Y")
                    : null;
                $item->effective_date = $item->created_at
                    ? $item->created_at->format("m-d-Y")
                    : null;

                if ($item->transfer_mode === "Real Time Payment" && $item->created_at) {
                    $item->settlement_date = $item->created_at->format("m-d-Y");
                } else {
                    $item->settlement_date = null;
                }

                // 🔹 Hide fields
                unset(
                    $item->created_at,
                    $item->updated_at,
                    $item->payee,
                    $item->is_deleted,
                    $item->editable,
                    $item->check_no,
                    $item->printed_by,
                    $item->status,
                    $item->same_day_ach
                );

                return $item;
            });

            // 🔹 Helper function for date formatting
            function formatDates($item, $fields = ['start_date', 'end_date', 'created_at', 'updated_at'])
            {
                foreach ($fields as $field) {
                    if (!empty($item[$field])) {
                        $item[$field] = date("m-d-Y", strtotime($item[$field]));
                    }
                }
                return $item;
            }

            $transactions = collect($transactions)->map(function ($item) {
                $item = formatDates($item);
                if (isset($item['payee_account_no'])) {
                    $accNo = $item['payee_account_no'];
                    $item['payee_account_no'] = str_repeat('X', strlen($accNo) - 4) . substr($accNo, -4);
                }
                return $item;
            });

            // 🔹 Prepare Response Data
            $responseData = [
                "transaction_id" => $transactionId,
                "user" => [
                    "id" => $user->id,
                    "name" => $user->name,
                    "email" => $user->email,
                ],
                "transactions" => $transactions,
                "pagination" => [
                    "total" => $paginator->total(),
                    "per_page" => $paginator->perPage(),
                    "current_page" => $paginator->currentPage(),
                    "last_page" => $paginator->lastPage(),
                    "from" => $paginator->firstItem(),
                    "to" => $paginator->lastItem(),
                ]
            ];

            // 🔐 Encrypt + Decrypt internally
            $encryptedData = $this->encryptionService->encrypt($responseData);
            $decryptedData = $this->encryptionService->decrypt($encryptedData);

            return ApiResponse::success($decryptedData, "Transactions fetched successfully");
        } catch (\Exception $e) {
            Log::channel("api")->error("Transaction fetch failed", [
                "transaction_id" => $transactionId,
                "error" => $e->getMessage(),
                "trace" => $e->getTraceAsString(),
            ]);

            return ApiResponse::error("Failed to fetch transactions", 500, [
                "transaction_id" => $transactionId,
            ]);
        }
    }

    public function receiveData(Request $request)
    {
        $transactionId = (string) Str::uuid(); // Unique transaction ID

        try {
            $userId = Auth::id();
            $user = Auth::user(); // Logged in user details

            Log::channel("api")->info("Fetching receivables for user", [
                "transaction_id" => $transactionId,
                "user_id" => $userId,
                "filters" => $request->only(["payee_id", "recurring_id"]),
            ]);

            // 🔹 Base Query
            $query = Receivable::where("is_deleted", false)
                ->where("user_id", $userId)
                ->with("payee:id,payee_name");

            // 🔹 Optional Filters
            if ($request->has("payee_id")) {
                $query->where("payee_id", $request->payee_id);
            }

            if ($request->has("recurring_id")) {
                $query->where("ref_id", $request->recurring_id)
                    ->whereNotNull("Recurring", "");
            }

            // 🔹 Fetch Data
            $receivables = $query->get()->map(function ($item) {
                $item->status_text = $this->getStatusText($item->status);
                $item->{'Merchant/PayeeName'} = $item->payee?->payee_name;

                // 🔹 Memo split
                if (!empty($item->memo)) {
                    [$scheduleName, $schedulePurpose] = array_pad(explode('-', $item->memo, 2), 2, '');
                    $item->{'Schedule Name'} = trim($scheduleName);
                    $item->{'Schedule Purpose'} = trim($schedulePurpose);
                    unset($item->memo);
                }

                // 🔹 RTN
                if (!empty($item->rtn_date)) {
                    $item->return_date = \Carbon\Carbon::parse($item->rtn_date)->format("m-d-Y");
                    unset($item->rtn_date);
                }

                // 🔹 Deposit Date
                if (empty($item->rtn_code)) {
                    if (in_array($item->payment_from, ["Wallet", "RTP Bank", "Wallet/RTP Bank"])) {
                        $item->deposit_date = $item->created_at?->format("m-d-Y");
                    } else {
                        $item->deposit_date = $item->created_at
                            ? $item->created_at->addDays(3)->format("m-d-Y")
                            : null;
                    }
                } else {
                    $item->deposit_date = null;
                }

                // 🔹 Format Dates
                $item->organization_date = $item->created_at?->format("m-d-Y");
                $item->effective_date = $item->created_at?->format("m-d-Y");

                $item->settlement_date = ($item->payment_from === "Wallet/RTP Bank" && $item->created_at)
                    ? $item->created_at->format("m-d-Y")
                    : null;

                // 🔹 Confirmation
                $item->confirmation = $item->ref_id;
                unset($item->ref_id);

                // ✅ Keep payee_id & recurring_id
                $item->payee_id = $item->payee_id;
                $item->recurring_id = $item->recurring_id;

                // Hide unwanted fields
                unset(
                    $item->created_at,
                    $item->updated_at,
                    $item->payee,
                    $item->is_deleted,
                    $item->editable,
                    $item->check_no,
                    $item->printed_by,
                    $item->status,
                    $item->same_day_ach
                );

                return $item;
            });

            // 🔹 Format and Mask Account Numbers
            $receivables = $receivables->map(function ($item) {
                $this->formatDates($item, ['start_date', 'end_date']);
                if (!empty($item['payee_account_no'])) {
                    $accNo = $item['payee_account_no'];
                    $item['payee_account_no'] = str_repeat('X', strlen($accNo) - 4) . substr($accNo, -4);
                }
                return $item;
            });

            // 🔹 Final Response
            $responseData = [
                "transaction_id" => $transactionId,
                "user" => [
                    "id" => $user->id,
                    "name" => $user->name,
                    "email" => $user->email,
                ],
                "receivables" => $receivables,
            ];

            // Encrypt + Decrypt internally
            $encryptedData = $this->encryptionService->encrypt($responseData);
            $decryptedData = $this->encryptionService->decrypt($encryptedData);

            return ApiResponse::success($decryptedData, "Receivables fetched successfully");
        } catch (\Exception $e) {
            Log::channel("api")->error("Receivables fetch failed", [
                "transaction_id" => $transactionId,
                "error" => $e->getMessage(),
                "trace" => $e->getTraceAsString(),
            ]);

            return ApiResponse::error("Failed to fetch receivables", 500, [
                "transaction_id" => $transactionId,
            ]);
        }
    }

    /**
     * Helper: Format dates safely
     */
    private function formatDates(&$item, $fields = ['start_date', 'end_date', 'created_at', 'updated_at'])
    {
        foreach ($fields as $field) {
            if (!empty($item[$field])) {
                $item[$field] = date("m-d-Y", strtotime($item[$field]));
            }
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/transactions/{id}",
     *     tags={"Transactions"},
     *     summary="Get a transaction or receivable by confirmation id",
     *     description="Returns the transaction or receivable details based on the provided confirmation id",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Confirmation ID from Transaction or receivable",
     *         @OA\Schema(type="string", example="240712021905968TEST")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(
     *                     type="object",
     *                     @OA\Property(
     *                         property="status",
     *                         type="integer",
     *                         example=200
     *                     ),
     *                     @OA\Property(
     *                         property="message",
     *                         type="string",
     *                         example="Transactions fetched successfully"
     *                     )
     *                 ),
     *                 @OA\Schema(ref="#/components/schemas/TransactionByConfirmationID")
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Record Not Found",
     *         @OA\JsonContent(ref="#/components/schemas/NotFound")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(ref="#/components/schemas/Unauthorized")
     *     )
     * )
     */
    public function getById($id)
    {
        $transactionId = (string) Str::uuid(); // Unique transaction tracking ID

        try {
            $userId = auth()->id();

            Log::channel("api")->info("Fetching transaction/receivable by ID", [
                "transaction_id" => $transactionId,
                "user_id" => $userId,
                "id" => $id,
            ]);

            // ✅ Check Transaction first
            $transaction = Transaction::where("is_deleted", false)
                ->where("confirmation", $id)
                ->where("user_id", $userId)
                ->with("payee:id,payee_name")
                ->first();

            if ($transaction) {
                $transaction->status_text = $this->getStatusText($transaction->status);
                $transaction->{'Merchant/PayeeName'} = $transaction->payee ? $transaction->payee->payee_name : null;

                if (isset($transaction->memo)) {
                    $parts = explode('-', $transaction->memo, 2);
                    $transaction->{'Schedule Name'} = trim($parts[0] ?? '');
                    $transaction->{'Schedule Purpose'} = trim($parts[1] ?? '');
                    unset($transaction->memo);
                }

                if (!empty($transaction->rtn_code)) {
                    $transaction->rtn_code = $transaction->rtn_code;
                }

                if (isset($transaction->rtn_date)) {
                    $transaction->return_date = $transaction->rtn_date
                        ? Carbon::parse($transaction->rtn_date)->format("m-d-Y")
                        : null;
                    unset($transaction->rtn_date);
                }

                if (empty($transaction->rtn_code)) {
                    $transaction->deposit_date = ($transaction->transfer_mode === "Real Time Payment" && $transaction->created_at)
                        ? date("m-d-Y", strtotime($transaction->created_at))
                        : ($transaction->created_at ? date("m-d-Y", strtotime($transaction->created_at . " +3 day")) : null);
                } else {
                    $transaction->deposit_date = null;
                }

                $transaction->organization_date = $transaction->created_at ? $transaction->created_at->format("m-d-Y") : null;
                $transaction->effective_date = $transaction->created_at ? $transaction->created_at->format("m-d-Y") : null;
                $transaction->start_date = $transaction->start_date ? Carbon::parse($transaction->start_date)->format("m-d-Y") : null;
                $transaction->end_date = $transaction->end_date ? Carbon::parse($transaction->end_date)->format("m-d-Y") : null;
                $transaction->settlement_date = ($transaction->transfer_mode === "Real Time Payment" && $transaction->created_at)
                    ? $transaction->created_at->format("m-d-Y")
                    : null;

                if (isset($transaction->payee_account_no)) {
                    $accNo = $transaction->payee_account_no;
                    $transaction->payee_account_no = str_repeat('X', strlen($accNo) - 4) . substr($accNo, -4);
                }

                unset(
                    $transaction->created_at,
                    $transaction->updated_at,
                    $transaction->user_id,
                    $transaction->payee,
                    $transaction->is_deleted,
                    $transaction->editable,
                    $transaction->check_no,
                    $transaction->printed_by,
                    $transaction->status,
                    $transaction->same_day_ach,
                    $transaction->trans_comment,
                    $transaction->type
                );

                $decrypted = $this->encryptionService->decrypt($this->encryptionService->encrypt($transaction));

                return ApiResponse::success([
                    "transaction_id" => $transactionId,
                    "source" => "transactions",
                    "data" => $decrypted,
                ]);
            }

            // ✅ Check Receivable
            $receivable = Receivable::where("is_deleted", false)
                ->where("ref_id", $id)
                ->where("user_id", $userId)
                ->with("payee:id,payee_name")
                ->first();

            if ($receivable) {
                $receivable->status_text = $this->getStatusText($receivable->status);
                $receivable->{'Merchant/PayeeName'} = $receivable->payee ? $receivable->payee->payee_name : null;

                if (isset($receivable->memo)) {
                    $receivable->{'Shedule Purpose/Memo'} = $receivable->memo;
                    unset($receivable->memo);
                }

                if (!empty($receivable->rtn_code)) {
                    $receivable->rtn_code = $receivable->rtn_code;
                }

                if (isset($receivable->rtn_date)) {
                    $receivable->return_date = $receivable->rtn_date
                        ? Carbon::parse($receivable->rtn_date)->format("m-d-Y")
                        : null;
                    unset($receivable->rtn_date);
                }

                if (empty($receivable->rtn_code)) {
                    $receivable->deposit_date = in_array($receivable->payment_from, ["Wallet/RTP Bank"]) && $receivable->created_at
                        ? date("m-d-Y", strtotime($receivable->created_at))
                        : ($receivable->created_at ? date("m-d-Y", strtotime($receivable->created_at . " +3 day")) : null);
                } else {
                    $receivable->deposit_date = null;
                }

                $receivable->organization_date = $receivable->created_at ? $receivable->created_at->format("m-d-Y") : null;
                $receivable->effective_date = $receivable->created_at ? $receivable->created_at->format("m-d-Y") : null;
                $receivable->start_date = $receivable->start_date ? Carbon::parse($receivable->start_date)->format("m-d-Y") : null;
                $receivable->end_date = $receivable->end_date ? Carbon::parse($receivable->end_date)->format("m-d-Y") : null;
                $receivable->settlement_date = in_array($receivable->payment_from, ["Wallet", "RTP Bank"]) && $receivable->created_at
                    ? $receivable->created_at->format("m-d-Y")
                    : null;

                if (isset($receivable->payee_account_no)) {
                    $accNo = $receivable->payee_account_no;
                    $receivable->payee_account_no = str_repeat('X', strlen($accNo) - 4) . substr($accNo, -4);
                }

                $receivable->confirmation = $receivable->ref_id;
                unset($receivable->ref_id);

                unset(
                    $receivable->created_at,
                    $receivable->updated_at,
                    $receivable->user_id,
                    $receivable->payee,
                    $receivable->is_deleted,
                    $receivable->editable,
                    $receivable->check_no,
                    $receivable->printed_by,
                    $receivable->status,
                    $receivable->same_day_ach,
                    $receivable->trans_comment,
                    $receivable->type,
                    $receivable->tokenize_payment
                );

                $decrypted = $this->encryptionService->decrypt($this->encryptionService->encrypt($receivable));

                return ApiResponse::success([
                    "transaction_id" => $transactionId,
                    "source" => "receivables",
                    "data" => $decrypted,
                ]);
            }

            Log::channel("api")->warning("Record not found", [
                "transaction_id" => $transactionId,
                "user_id" => $userId,
                "id" => $id,
            ]);

            return ApiResponse::error("Record not found", 404, ["transaction_id" => $transactionId]);
        } catch (\Exception $e) {
            Log::channel("api")->error("Failed to fetch transaction by ID", [
                "transaction_id" => $transactionId,
                "user_id" => auth()->id(),
                "id" => $id,
                "error" => $e->getMessage(),
                "trace" => $e->getTraceAsString(),
            ]);

            return ApiResponse::error("Internal server error", 500, ["transaction_id" => $transactionId]);
        }
    }
    /**
     * @OA\Get(
     *     path="/api/v1/transactions/date-range",
     *     tags={"Transactions"},
     *     summary="Get all transactions and receivables by originated date range",
     *     description="Returns all transactions and receivables where originated_date is between 'from' and 'to' dates",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="from",
     *         in="query",
     *         required=true,
     *         description="Start date in YYYY-MM-DD format",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="to",
     *         in="query",
     *         required=true,
     *         description="End date in YYYY-MM-DD format",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         description="Page number (default 1)",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         description="Number of records per page (default 10)",
     *         @OA\Schema(type="integer", example=10)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(
     *                     type="object",
     *                     @OA\Property(
     *                         property="status",
     *                         type="integer",
     *                         example=200
     *                     ),
     *                     @OA\Property(
     *                         property="message",
     *                         type="string",
     *                         example="Transactions fetched successfully"
     *                     )
     *                 ),
     *                 @OA\Schema(ref="#/components/schemas/TransactionByDateRange")
     *             }
     *         )
     *     ),
     *     @OA\Response(response=400, description="Invalid date format", @OA\JsonContent(ref="#/components/schemas/BadRequest")),
     *     @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/Unauthorized"))
     * )
     */
    public function getByDateRange(Request $request)
    {
        $transactionId = (string) Str::uuid();
        $from = $request->query("from");
        $to = $request->query("to");
        $userId = auth()->id();

        try {
            // Validate date format
            if (!strtotime($from) || !strtotime($to)) {
                return ApiResponse::error("Invalid date format. Use YYYY-MM-DD.", 400, [
                    "transaction_id" => $transactionId
                ]);
            }

            $startDate = Carbon::parse($from)->subDay(); // from - 1 day
            $endDate   = Carbon::parse($to)->addDay();   // to + 1 day

            // ✅ Pagination inputs
            $perPage = (int) $request->get("per_page", 10);
            $page = (int) $request->get("page", 1);

            Log::channel("api")->info(
                "Fetching combined transactions & receivables by date range",
                [
                    "transaction_id" => $transactionId,
                    "user_id" => $userId,
                    "from" => $from,
                    "to" => $to,
                    "page" => $page,
                    "per_page" => $perPage,
                ]
            );

            // Helper function to format transactions/receivables
            $formatRecord = function ($record, $source) {
                $record->source = $source;
                $record->status_text = $this->getStatusText($record->status);
                $record->{'Merchant/PayeeName'} = $record->payee ? $record->payee->payee_name : null;

                $record->organization_date = $record->created_at ? $record->created_at->format("m-d-Y") : null;
                $record->effective_date = $record->created_at ? $record->created_at->format("m-d-Y") : null;
                $record->payment_date = $record->payment_date ? Carbon::parse($record->payment_date)->format("m-d-Y") : null;

                // Settlement Date
                $record->settlement_date = ($source === "transactions")
                    ? ($record->transfer_mode === "Real Time Payment" && $record->created_at ? $record->created_at->format("m-d-Y") : null)
                    : (in_array($record->payment_from, ["Wallet", "RTP Bank"]) && $record->created_at ? $record->created_at->format("m-d-Y") : null);

                // Deposit Date
                if (empty($record->rtn_code)) {
                    if ($source === "transactions") {
                        $record->deposit_date = ($record->transfer_mode === "Real Time Payment" && $record->created_at)
                            ? $record->created_at->format("m-d-Y")
                            : ($record->created_at ? $record->created_at->copy()->addDays(3)->format("m-d-Y") : null);
                    } else {
                        $record->deposit_date = ($record->created_at && preg_match('/Wallet|RTP Bank/', $record->payment_from))
                            ? $record->created_at->format("m-d-Y")
                            : ($record->created_at ? $record->created_at->copy()->addDays(3)->format("m-d-Y") : null);
                    }
                } else {
                    $record->deposit_date = null;
                }

                // Return Date
                $record->return_date = $record->rtn_date ? Carbon::parse($record->rtn_date)->format("m-d-Y") : null;
                unset($record->rtn_date);

                // Rename memo
                if (isset($record->memo)) {
                    $parts = explode('-', $record->memo, 2);
                    $record->{'Schedule Name'} = trim($parts[0] ?? '');
                    $record->{'Schedule Purpose'} = trim($parts[1] ?? '');
                    unset($record->memo);
                }

                // Mask account number
                if (isset($record->payee_account_no)) {
                    $accNo = $record->payee_account_no;
                    $record->payee_account_no = str_repeat('X', strlen($accNo) - 4) . substr($accNo, -4);
                }

                // Replace ref_id with confirmation for receivables
                if ($source === "receivables" && isset($record->ref_id)) {
                    $record->confirmation = $record->ref_id;
                    unset($record->ref_id);
                }

                // Hide sensitive fields
                unset(
                    $record->created_at,
                    $record->updated_at,
                    $record->user_id,
                    $record->payee,
                    $record->is_deleted,
                    $record->editable,
                    $record->check_no,
                    $record->printed_by,
                    $record->status,
                    $record->same_day_ach,
                    $record->trans_comment,
                    $record->type
                );

                // Encrypt → Decrypt
                return $this->encryptionService->decrypt($this->encryptionService->encrypt($record));
            };

            // Fetch Transactions
            $transactions = Transaction::where("is_deleted", false)
                ->where("user_id", $userId)
                ->whereBetween("created_at", [$startDate, $endDate])
                ->with("payee:id,payee_name")
                ->get()
                ->map(fn($t) => $formatRecord($t, "transactions"));

            // Fetch Receivables
            $receivables = Receivable::where("is_deleted", false)
                ->where("user_id", $userId)
                ->whereBetween("created_at", [$startDate, $endDate])
                ->with("payee:id,payee_name")
                ->get()
                ->map(fn($r) => $formatRecord($r, "receivables"));

            // Merge and sort
            $results = $transactions->merge($receivables)->sortByDesc("organization_date")->values();

            // 🔹 Manual pagination (since merging collections)
            $total = $results->count();
            $offset = ($page - 1) * $perPage;
            $paginated = $results->slice($offset, $perPage)->values();

            $paginationMeta = [
                "total" => $total,
                "per_page" => $perPage,
                "current_page" => $page,
                "last_page" => ceil($total / $perPage),
                "from" => $offset + 1,
                "to" => min($offset + $perPage, $total)
            ];

            return ApiResponse::success([
                "transaction_id" => $transactionId,
                "data" => $paginated,
                "pagination" => $paginationMeta,
            ]);
        } catch (\Exception $e) {
            Log::channel("api")->error("Failed to fetch combined records", [
                "transaction_id" => $transactionId,
                "user_id" => $userId,
                "error" => $e->getMessage(),
                "trace" => $e->getTraceAsString(),
            ]);

            return ApiResponse::error("Internal server error", 500, ["transaction_id" => $transactionId]);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/transactions/{id}/cancel",
     *     tags={"Transactions"},
     *     summary="Cancel a transaction or receivable by ID",
     *     description="Cancels a transaction or receivable using its unique ID",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Transaction or receivable ID",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Cancelled successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(
     *                     type="object",
     *                     @OA\Property(
     *                         property="status",
     *                         type="integer",
     *                         example=200
     *                     ),
     *                     @OA\Property(
     *                         property="message",
     *                         type="string",
     *                         example="Transactions fetched successfully"
     *                     )
     *                 ),
     *                 @OA\Schema(ref="#/components/schemas/CancelTransactionByConfirmationId")
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Record Not Found",
     *         @OA\JsonContent(ref="#/components/schemas/NotFound")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(ref="#/components/schemas/Unauthorized")
     *     )
     * )
     */
    public function cancelTransaction($id)
    {
        $transactionId = (string) Str::uuid();
        $userId = auth()->id();

        if (!$userId) {
            return ApiResponse::error("Unauthorized. Please login first.", 401, [
                "transaction_id" => $transactionId
            ]);
        }

        try {
            DB::beginTransaction();

            Log::channel("api")->info("Attempting to cancel transaction or receivable", [
                "transaction_id" => $transactionId,
                "user_id" => $userId,
                "id" => $id,
            ]);

            // Helper to format cancelled record
            $formatCancelledRecord = function ($record, $source) {
                $record->status_text = $this->getStatusText($record->status);
                $payeeName = optional($record->payee)->payee_name ?? null;
                $settlementDate = ($source === "transactions")
                    ? ($record->transfer_mode === "Real Time Payment" ? now()->format("m-d-Y") : null)
                    : ($record->payment_from === "Wallet/RTP Bank" ? now()->format("m-d-Y") : null);

                $responseData = collect($record)->except(["created_at", "updated_at"])->toArray();

                // Dates
                $responseData["created_date"] = Carbon::parse($record->created_at)->format("m-d-Y");
                $responseData["originated_date"] = $record->originated_date
                    ? Carbon::parse($record->originated_date)->format("m-d-Y")
                    : null;
                $responseData["effective_date"] = $record->effective_date
                    ? Carbon::parse($record->effective_date)->format("m-d-Y")
                    : null;
                $responseData["payment_date"] = $record->payment_date
                    ? Carbon::parse($record->payment_date)->format("m-d-Y")
                    : null;
                $responseData["deposit_date"] = !empty($record->rtn_code)
                    ? null
                    : ($record->created_at
                        ? (($source === "transactions"
                            ? ($record->transfer_mode === "Real Time Payment"
                                ? Carbon::parse($record->created_at)->format("m-d-Y")
                                : Carbon::parse($record->created_at)->addDay(3)->format("m-d-Y"))
                            : (preg_match('/Wallet|RTP Bank/', $record->payment_from)
                                ? Carbon::parse($record->created_at)->format("m-d-Y")
                                : Carbon::parse($record->created_at)->addDay(3)->format("m-d-Y")))
                        )
                        : null);

                // Memo → Schedule Name / Purpose
                if (isset($responseData["memo"])) {
                    $parts = explode('-', $responseData["memo"], 2);
                    $responseData["Schedule Name"] = trim($parts[0] ?? '');
                    $responseData["Schedule Purpose"] = trim($parts[1] ?? ($parts[0] ?? ''));
                    unset($responseData["memo"]);
                }

                // Return date
                $responseData["return_date"] = $record->rtn_date
                    ? Carbon::parse($record->rtn_date)->format("m-d-Y")
                    : null;
                unset($responseData["rtn_date"]);

                // rtn_code
                if (!empty($record->rtn_code)) {
                    $responseData["rtn_code"] = $record->rtn_code;
                }

                $responseData["Merchant/PayeeName"] = $payeeName;
                $responseData["settlement_date"] = $settlementDate;

                // Mask account number
                if (isset($responseData["payee_account_no"])) {
                    $accNo = $responseData["payee_account_no"];
                    $responseData["payee_account_no"] = str_repeat('X', strlen($accNo) - 4) . substr($accNo, -4);
                }

                // Include payee info
                if ($record->payee) {
                    $payee = collect($record->payee)
                        ->except(["user_id", "created_at", "updated_at"])
                        ->toArray();
                    $responseData["payee"] = $payee;
                }

                // Hide sensitive fields
                unset(
                    $responseData["is_deleted"],
                    $responseData["editable"],
                    $responseData["check_no"],
                    $responseData["printed_by"],
                    $responseData["status"],
                    $responseData["same_day_ach"],
                    $responseData["trans_comment"],
                    $responseData["type"]
                );

                return $this->encryptionService->decrypt($this->encryptionService->encrypt($responseData));
            };

            // Cancel Transaction
            $transaction = Transaction::where("confirmation", $id)
                ->where("user_id", $userId)
                ->with("payee")
                ->first();

            if ($transaction) {
                $transaction->status = 10;
                $transaction->save();

                DB::commit();
                event(new TransactionCancelled($transaction));

                $decrypted = $formatCancelledRecord($transaction, "transactions");

                return ApiResponse::success([
                    "transaction_id" => $transactionId,
                    "source" => "transactions",
                    "message" => "Transaction cancelled successfully",
                    "status" => "cancelled",
                    "data" => $decrypted
                ]);
            }

            // Cancel Receivable
            $receivable = Receivable::where("ref_id", $id)
                ->where("user_id", $userId)
                ->with("payee")
                ->first();

            if ($receivable) {
                $receivable->status = 10;
                $receivable->save();

                DB::commit();
                event(new ReceivableCancelled($receivable));

                $decrypted = $formatCancelledRecord($receivable, "receivables");

                return ApiResponse::success([
                    "transaction_id" => $transactionId,
                    "source" => "receivables",
                    "message" => "Receivable cancelled successfully",
                    "status" => "cancelled",
                    "data" => $decrypted
                ]);
            }

            DB::rollBack();

            return ApiResponse::error("Record not found", 404, ["transaction_id" => $transactionId]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::channel("api")->error("Transaction cancel failed", [
                "transaction_id" => $transactionId,
                "user_id" => $userId,
                "id" => $id,
                "error" => $e->getMessage(),
            ]);

            return ApiResponse::error("Failed to cancel the transaction", 500, ["transaction_id" => $transactionId]);
        }
    }
    private function getStatusText($status)
    {
        return match ((int) $status) {
            0 => "Returned",
            1 => "Cleared",
            10 => "Cancelled",
            101 => "Pending",
            default => "Unknown",
        };
    }

    /**
     * @OA\Get(
     *     path="/api/v1/payees",
     *     tags={"Payees / Merchants"},
     *     summary="Get all payees / merchants",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         description="Page number (default: 1)",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         description="Number of records per page (default: 10)",
     *         @OA\Schema(type="integer", example=10)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(
     *                     type="object",
     *                     @OA\Property(
     *                         property="status",
     *                         type="integer",
     *                         example=200
     *                     ),
     *                     @OA\Property(
     *                         property="message",
     *                         type="string",
     *                         example="Transactions fetched successfully"
     *                     )
     *                 ),
     *                 @OA\Schema(ref="#/components/schemas/GetPayeeSchema")
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(ref="#/components/schemas/Unauthorized")
     *     )
     * )
     */
    public function getPayees(Request $request)
    {
        try {
            $userId = Auth::id();

            if (!$userId) {
                return ApiResponse::error("Unauthorized. Please login first.", 401);
            }

            Log::channel("api")->info("Fetching payees for user", ["user_id" => $userId]);

            // 🔹 Pagination input
            $perPage = (int) $request->get("per_page", 10);
            $page = (int) $request->get("page", 1);

            // Encrypt & decrypt user ID internally
            $encryptedUserId = $this->encryptionService->encrypt($userId);
            $decryptedUserId = $this->encryptionService->decrypt($encryptedUserId);

            // 🔹 Base Query
            $query = Payee::where("user_id", $userId)->orderByDesc("created_at");

            // 🔹 Paginate
            $paginator = $query->paginate($perPage, ["*"], "page", $page);

            $payees = collect($paginator->items())->map(function ($payee) {
                // 🔹 Primary Bank
                $primaryBank = PayeeBank::where("payee_id", $payee->id)->first();
                $primaryBankData = $primaryBank ? [
                    "id"                  => $primaryBank->id,
                    "payee_id"            => $primaryBank->payee_id,
                    "account_holder_name" => $primaryBank->account_holder_name,
                    "routing_no"          => $primaryBank->routing_no,
                    "account_no"          => $primaryBank->account_no,
                    "account_type"        => $primaryBank->account_type,
                    "created_at"          => $primaryBank->created_at?->format('m-d-Y'),
                    "updated_at"          => $primaryBank->updated_at?->format('m-d-Y'),
                ] : null;

                // 🔹 Additional Banks
                $additionalBanks = PayeeInternationalBank::where("payee_id", $payee->id)->get()
                    ->map(fn($bank) => [
                        "id"                  => $bank->unique_id,
                        "payee_id"            => $bank->payee_id,
                        "account_holder_name" => $bank->account_holder_name,
                        "routing_no"          => $bank->routing_no,
                        "account_no"          => $bank->account_no,
                        "account_type"        => $bank->account_type,
                        "created_at"          => $bank->created_at?->format('m-d-Y'),
                        "updated_at"          => $bank->updated_at?->format('m-d-Y'),
                    ]);

                // 🔹 Payee Data (hide sensitive fields)
                $payeeData = $payee->makeHidden(['unique_id', 'account_no', 'created_at', 'updated_at', 'nickname'])->toArray();
                $payeeData['created_at'] = $payee->created_at?->format('m-d-Y');
                $payeeData['updated_at'] = $payee->updated_at?->format('m-d-Y');
                $payeeData['email'] = $payee->email ? $this->encryptionService->decrypt($payee->email) : null;
                $payeeData['phone_no'] = $payee->phone_no ? $this->encryptionService->decrypt($payee->phone_no) : null;

                return [
                    "data"             => $payeeData,
                    "Primary Account"  => $primaryBankData,
                    "Additional Banks" => $additionalBanks,
                ];
            });

            return ApiResponse::success([
                "user_id" => $decryptedUserId,
                "source"  => "payees",
                "payees"  => $payees,
                "pagination" => [
                    "total"         => $paginator->total(),
                    "per_page"      => $paginator->perPage(),
                    "current_page"  => $paginator->currentPage(),
                    "last_page"     => $paginator->lastPage(),
                    "from"          => $paginator->firstItem(),
                    "to"            => $paginator->lastItem(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::channel("api")->error("Failed to fetch payees", [
                "error" => $e->getMessage(),
                "trace" => $e->getTraceAsString(),
            ]);

            return ApiResponse::error("Failed to fetch payees.", 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/payees/{id}",
     *     tags={"Payees / Merchants"},
     *     summary="Get a specific payee by ID",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Payee ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(
     *                     type="object",
     *                     @OA\Property(
     *                         property="status",
     *                         type="integer",
     *                         example=200
     *                     ),
     *                     @OA\Property(
     *                         property="message",
     *                         type="string",
     *                         example="Transactions fetched successfully"
     *                     )
     *                 ),
     *                 @OA\Schema(ref="#/components/schemas/GetPayeeByIdSchema")
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Record Not Found",
     *         @OA\JsonContent(ref="#/components/schemas/NotFound")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(ref="#/components/schemas/Unauthorized")
     *     )
     * )
     */
    public function getPayeeId($id)
    {
        try {
            $userId = Auth::id();

            if (!$userId) {
                return ApiResponse::error("Unauthorized. Please login first.", 401);
            }

            Log::channel("api")->info("Fetching specific payee", [
                "user_id" => $userId,
                "payee_id" => $id,
            ]);

            // Encrypt & Decrypt internally (optional, for internal logic)
            $encryptedUserId = $this->encryptionService->encrypt($userId);
            $decryptedUserId = $this->encryptionService->decrypt($encryptedUserId);

            // Fetch payee
            $payee = Payee::where("id", $id)
                ->where("user_id", $userId)
                ->first();

            if (!$payee) {
                return ApiResponse::error("Record not found", 404);
            }

            // 🔹 Fetch primary bank
            $primaryBank = PayeeBank::where("payee_id", $payee->id)->first();
            $primaryBankData = $primaryBank ? [
                "id"                  => $primaryBank->id,
                "payee_id"            => $primaryBank->payee_id,
                "account_holder_name" => $primaryBank->account_holder_name,
                "routing_no"          => $primaryBank->routing_no,
                "account_no"          => str_repeat('X', strlen($primaryBank->account_no) - 4) . substr($primaryBank->account_no, -4),
                "account_type"        => $primaryBank->account_type,
                "created_at"          => $primaryBank->created_at ? $primaryBank->created_at->format('m-d-Y') : null,
                "updated_at"          => $primaryBank->updated_at ? $primaryBank->updated_at->format('m-d-Y') : null,
            ] : null;

            // 🔹 Fetch additional banks
            $additionalBanks = PayeeInternationalBank::where("payee_id", $payee->id)->get()
                ->map(function ($bank) {
                    return [
                        "id"                  => $bank->unique_id,
                        "payee_id"            => $bank->payee_id,
                        "account_holder_name" => $bank->account_holder_name,
                        "routing_no"          => $bank->routing_no,
                        "account_no"          => str_repeat('X', strlen($bank->account_no) - 4) . substr($bank->account_no, -4),
                        "account_type"        => $bank->account_type,
                        "created_at"          => $bank->created_at ? $bank->created_at->format('m-d-Y') : null,
                        "updated_at"          => $bank->updated_at ? $bank->updated_at->format('m-d-Y') : null,
                    ];
                });

            // 🔹 Prepare Payee data (hide sensitive fields)
            $payeeData = $payee->makeHidden(['unique_id', 'account_no', 'created_at', 'updated_at', 'nickname']);

            return ApiResponse::success([
                "user_id"          => $decryptedUserId,
                "source"           => "payees",
                "data"             => $payeeData,
                "Primary Account"  => $primaryBankData,
                "Additional Banks" => $additionalBanks,
            ]);
        } catch (\Exception $e) {
            Log::channel("api")->error("Failed to fetch payee", [
                "error" => $e->getMessage(),
                "trace" => $e->getTraceAsString(),
            ]);

            return ApiResponse::error("Failed to fetch payee.", 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/payees",
     *     summary="Create a new payee and associated bank info",
     *     tags={"Payees / Merchants"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={
     *                 "payee_type", "payee_name",
     *                 "first_name", "last_name", "email", "payee_id",
     *                 "address_line1", "city", "state", "zip", "country",
     *                 "account_name", "routing_number", "account_number",
     *                 "confirm_account_number", "account_type"
     *             },
     *             @OA\Property(property="payee_type", type="string", example="customer"),
     *             @OA\Property(property="payee_name", type="string", example="api_payee"),
     *             @OA\Property(property="first_name", type="string", example="demo"),
     *             @OA\Property(property="last_name", type="string", example="tester"),
     *             @OA\Property(property="email", type="string", example="customerdemo@gmail.com"),
     *             @OA\Property(property="payee_id", type="string", example="5352637434"),
     *             @OA\Property(property="address_line1", type="string", example="demo,1232"),
     *             @OA\Property(property="address_line2", type="string", example="demo2,34322"),
     *             @OA\Property(property="phone", type="string", example="9876565744"),
     *             @OA\Property(property="city", type="string", example="demo_city"),
     *             @OA\Property(property="state", type="string", example="FL"),
     *             @OA\Property(property="zip", type="string", example="12345"),
     *             @OA\Property(property="country", type="string", example="US"),
     *             @OA\Property(property="account_name", type="string", example="Demo Tester"),
     *             @OA\Property(property="routing_number", type="string", example="867676564"),
     *             @OA\Property(property="account_number", type="string", example="123456789"),
     *             @OA\Property(property="confirm_account_number", type="string", example="123456789"),
     *             @OA\Property(
     *                 property="account_type",
     *                 type="string",
     *                 enum={"personal_savings", "personal_checking", "business_checking", "business_savings"},
     *                 example="business_savings"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(
     *                     type="object",
     *                     @OA\Property(
     *                         property="status",
     *                         type="integer",
     *                         example=200
     *                     ),
     *                     @OA\Property(
     *                         property="message",
     *                         type="string",
     *                         example="Transactions fetched successfully"
     *                     )
     *                 ),
     *                 @OA\Schema(ref="#/components/schemas/CreatePayeeSchema")
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation errors",
     *         @OA\JsonContent(ref="#/components/schemas/CreatePayeeValidationErrorSchema")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(ref="#/components/schemas/Unauthorized")
     *     )
     * )
     */
    public function createPayee(Request $request)
    {
        $user = auth()->user();

        if (!$user) {
            return ApiResponse::error("Invalid or expired token", 401);
        }

        $rules = [
            "payee_type" => "required|string|max:50",
            "payee_name" => "required|string|max:100",
            "first_name" => "required|string|max:50",
            "last_name" => "required|string|max:50",
            "email" => "required|email|max:50",
            "payee_external_id" => "required|string|max:50",
            "address_line1" => "required|string|max:255",
            "address_line2" => "nullable|string|max:255",
            "phone" => 'nullable|string|regex:/^\+?[0-9]{10,15}$/',
            "city" => "required|string|max:100",
            "state" => "required|string|max:100",
            "zip" => "required|string|max:20",
            "country" => "required|string|size:2",
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return ApiResponse::error($validator->errors()->first(), 422, $validator->errors());
        }

        $validated = $validator->validated();

        try {
            DB::beginTransaction();

            $merchant = Merchant::where("user_id", $user->id)->first();
            if (!$merchant) {
                throw new \Exception("Merchant not found");
            }

            $uniqueId = Str::random(6);
            $nickName = $validated["first_name"] . " " . $validated["last_name"];

            $payee = Payee::create([
                "user_id" => $user->id,
                "unique_id" => $uniqueId,
                "payee_type" => $validated["payee_type"],
                "payee_name" => $validated["payee_name"],
                "nickname" => $nickName,
                "email" => $validated["email"], // 🔐 Optionally encrypt
                "phone_no" => $validated["phone"] ?? null, // 🔐 Optionally encrypt
                "account_no" => $validated["payee_external_id"], // 🔐 Optionally encrypt
                "address1" => $validated["address_line1"],
                "address2" => $validated["address_line2"] ?? null,
                "city" => $validated["city"],
                "state" => $validated["state"],
                "zip" => $validated["zip"],
                "country" => $validated["country"],
            ]);

            DB::commit();

            Log::channel("api")->info("New payee created", [
                "user_id" => $user->id,
                "payee_id" => $payee->id,
                "ip" => $request->ip(),
                "email" => $validated["email"],
            ]);

            return ApiResponse::success([
                "id" => $payee->id,
                "message" => "Payee data has been successfully inserted",
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::channel("api")->error("Payee creation failed", [
                "user_id" => $user->id ?? null,
                "error" => $e->getMessage(),
                "trace" => $e->getTraceAsString(),
            ]);

            return ApiResponse::error("Payee creation failed: " . $e->getMessage(), 500);
        }
    }
    /**
     * @OA\Put(
     *     path="/api/v1/payees/{id}",
     *     summary="Update an existing payee ",
     *     tags={"Payees / Merchants"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="ID of the payee to update"
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="payee_type", type="string", example="customer"),
     *             @OA\Property(property="payee_name", type="string", example="updated_payee"),
     *             @OA\Property(property="first_name", type="string", example="Updated"),
     *             @OA\Property(property="last_name", type="string", example="Name"),
     *             @OA\Property(property="email", type="string", example="updateddemo@gmail.com"),
     *             @OA\Property(property="address_line1", type="string", example="123 updated street"),
     *             @OA\Property(property="address_line2", type="string", example="apt 5B"),
     *             @OA\Property(property="phone", type="string", example="9876543210"),
     *             @OA\Property(property="city", type="string", example="UpdatedCity"),
     *             @OA\Property(property="state", type="string", example="CA"),
     *             @OA\Property(property="zip", type="string", example="90210"),
     *             @OA\Property(property="country", type="string", example="US")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(
     *                     type="object",
     *                     @OA\Property(
     *                         property="status",
     *                         type="integer",
     *                         example=200
     *                     ),
     *                     @OA\Property(
     *                         property="message",
     *                         type="string",
     *                         example="Payee has been successfully updated"
     *                     )
     *                 ),
     *                 @OA\Schema(ref="#/components/schemas/UpdatePayeeSchema")
     *             }
     *         )
     *     ),
     *     @OA\Response(response=404, description="Payee not found", @OA\JsonContent(ref="#/components/schemas/PayeeNotFoundSchema")),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/CreatePayeeValidationErrorSchema")),
     *     @OA\Response(response=401, description="Unauthorized", @OA\JsonContent(ref="#/components/schemas/Unauthorized"))
     * )
     */
    public function updatePayee(Request $request, $id)
    {
        $rules = [
            "payee_type" => "sometimes|required|string|max:50",
            "payee_name" => "sometimes|required|string|max:100",
            "first_name" => "sometimes|required|string|max:50",
            "last_name" => "sometimes|required|string|max:50",
            "email" => "sometimes|required|email|max:50",
            "address_line1" => "sometimes|required|string|max:255",
            "address_line2" => "nullable|string|max:255",
            "phone" => 'nullable|string|regex:/^\+?[0-9]{10,15}$/',
            "city" => "sometimes|required|string|max:100",
            "state" => "sometimes|required|string|max:100",
            "zip" => "sometimes|required|string|max:20",
            "country" => "sometimes|required|string|size:2",
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json(["errors" => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            $userId = auth()->id();

            if (!$userId) {
                return response()->json(["message" => "Unauthorized"], 401);
            }

            // Encrypt/Decrypt for internal validation/logging
            $encryptedUserId = $this->encryptionService->encrypt($userId);
            $decryptedUserId = $this->encryptionService->decrypt(
                $encryptedUserId
            );

            Log::channel("api")->info("Updating payee", [
                "user_id" => $userId,
                "encrypted_user_id" => $encryptedUserId,
                "decrypted_user_id" => $decryptedUserId,
                "payee_id" => $id,
            ]);

            $payee = Payee::where("id", $id)
                ->where("user_id", $userId)
                ->firstOrFail();
            $payeeBank = PayeeBank::where("payee_id", $payee->id)->first();

            $data = $validator->validated();
            $nickname =
                ($data["first_name"] ?? $payee->first_name) .
                " " .
                ($data["last_name"] ?? $payee->last_name);

            // Update payee info
            $payee->update([
                "payee_type" => $data["payee_type"] ?? $payee->payee_type,
                "payee_name" => $data["payee_name"] ?? $payee->payee_name,
                "nickname" => $nickname,
                "email" => $data["email"] ?? $payee->email,
                "phone_no" => $data["phone"] ?? $payee->phone_no,
                "address1" => $data["address_line1"] ?? $payee->address1,
                "address2" => $data["address_line2"] ?? $payee->address2,
                "city" => $data["city"] ?? $payee->city,
                "state" => $data["state"] ?? $payee->state,
                "zip" => $data["zip"] ?? $payee->zip,
                "country" => $data["country"] ?? $payee->country,
            ]);

            // Update bank info if exists
            if ($payeeBank) {
                /*   $payeeBank->update([
                    "account_holder_name" =>
                        $data["account_name"] ??
                        $payeeBank->account_holder_name,
                    "routing_no" =>
                        $data["routing_number"] ?? $payeeBank->routing_no,
                    "account_no" =>
                        $data["account_number"] ?? $payeeBank->account_no,
                    "account_type" =>
                        $data["account_type"] ?? $payeeBank->account_type,
                ]);
                */
            }

            DB::commit();

            return ApiResponse::success([
                "message" => "Payee has been successfully updated",
                "payee" => $payee,
            ], "Success", 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json(["error" => "Payee not found"], 404);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::channel("api")->error("Failed to update payee", [
                "error" => $e->getMessage(),
                "trace" => $e->getTraceAsString(),
            ]);

            return response()->json(
                ["error" => "Update failed: " . $e->getMessage()],
                500
            );
        }
    }
    /**
     * @OA\Post(
     *     path="/api/v1/payees/{id}/bank-accounts",
     *     summary="Add a bank account to a payee",
     *     description="Associates a new bank account with an existing payee (merchant).",
     *     operationId="PayeeBankAccount",
     *     tags={"Payees / Merchants"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Payee ID",
     *         @OA\Schema(type="integer", example=123)
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"account_name", "routing_number", "account_number", "account_type"},
     *             @OA\Property(property="account_name", type="string", example="Demo Account"),
     *             @OA\Property(property="routing_number", type="string", example="021000021"),
     *             @OA\Property(property="account_number", type="string", example="123456789"),
     *             @OA\Property(property="confirm_account_number", type="string", example="123456789"),
     *             @OA\Property(property="account_type", type="string", example="business_savings")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Bank account added successfully"
     *     ),
     *     @OA\Response(response=400, description="Invalid input"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=404, description="Payee not found")
     * )
     */
    public function payeeBankAccount($id, Request $request)
    {
        try {

            // Step 1: Check Payee exists
            $payee = Payee::find($id);
            if (!$payee) {
                return response()->json(["message" => "Payee not found"], 404);
            }

            // Step 2: Get user_id from Payee
            $userId = $payee->user_id;
            $merchant = Merchant::where('user_id', $userId)->first();
            $timestamp = now();

            if (!$merchant) {
                \Log::channel('stack_with_db')->error("Merchant not found", [
                    'user_id' => $userId,
                    'timestamp' => $timestamp
                ]);
                return response()->json([
                    'message' => 'Merchant not found!'
                ], 500);
            }

            // Step 3: Define rules
            $rules = [
                'account_name'            => 'required|string|max:255',
                'routing_number'          => 'required|string|max:50',
                'account_number'          => 'required|string|max:50',
                'confirm_account_number'  => 'required|string|max:50|same:account_number',
                'account_type'            => 'required|string|in:checking,savings,business_checking,business_savings',
                'bank_acc_name'           => 'nullable|string|max:255',
                'city'                    => 'nullable|string|max:100',
                'state'                   => 'nullable|string|max:100',
                'zip'                     => 'nullable|string|max:20',
            ];

            // Step 4: Validate using Validator
            $validator = Validator::make($request->all(), $rules);
            if ($validator->fails()) {
                return response()->json([
                    "message" => "Validation failed",
                    "errors"  => $validator->errors()
                ], 422);
            }
            $validated = $validator->validated();

            // ✅ ACH Verify API Config
            $url = ($merchant->status == 0)
                ? 'https://devpayments.usiopay.com'
                : 'https://payments.usiopay.com';

            $verifyAccount = function ($routingNo, $accountNo) use ($merchant, $url, $userId, $timestamp) {
                $payload = [
                    'MerchantID'     => $merchant->merchant_id_credit,
                    'Login'          => $merchant->api_username_merchant,
                    "Password"       => $merchant->api_password,
                    "RoutingNumber"  => $routingNo,
                    "AccountNumber"  => $accountNo,
                ];

                \Log::channel('stack_with_db')->info("Sending VerifyACHAccount request", [
                    'user_id' => $userId,
                    'timestamp' => $timestamp,
                    'endpoint' => $url . '/2.0/payments.svc/JSON/VerifyACHAccount',
                    'payload' => $payload
                ]);

                $curl = curl_init();
                curl_setopt_array($curl, [
                    CURLOPT_URL => $url . '/2.0/payments.svc/JSON/VerifyACHAccount',
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_CUSTOMREQUEST => 'POST',
                    CURLOPT_POSTFIELDS => json_encode($payload),
                    CURLOPT_HTTPHEADER => [
                        'Content-Type: application/json',
                        'Accept: application/json',
                    ],
                ]);
                $response = curl_exec($curl);
                curl_close($curl);

                if ($response === false) {
                    \Log::channel('stack_with_db')->error("cURL error in VerifyACHAccount", [
                        'user_id' => $userId,
                        'timestamp' => $timestamp,
                        'error' => curl_error($curl)
                    ]);
                    return response()->json(['message' => 'cURL error: ' . curl_error($curl)], 500);
                }
                \Log::channel('stack_with_db')->info("Received response from VerifyACHAccount", [
                    'user_id' => $userId,
                    'timestamp' => $timestamp,
                    'response' => $response
                ]);

                return json_decode($response);
            };

            // ✅ Verify main account
            $mainData = $verifyAccount(
                $validated['routing_number'],
                $validated['account_number']
            );

            if ($mainData->Status == 'failure') {
                $cleanMessage = str_replace(['5078:', '5079:'], '', $mainData->Message);
                return response()->json(['message' => $cleanMessage], 500);
            } elseif ($mainData->Status == 'success' && $mainData->Message == 'Account Closed') {
                return response()->json(['message' => 'Your account has been closed!'], 500);
            } elseif ($mainData->Status == 'success' && !empty($mainData->Confirmation)) {
                // ✅ Generate random string
                $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
                $randomString = '';
                for ($i = 0; $i < 5; $i++) {
                    $randomString .= $characters[rand(0, strlen($characters) - 1)];
                }
                $nick_name = $validated['account_name'] . ' ' . $randomString;
            } else {
                return response()->json(['message' => 'Unknown verification response'], 500);
            }
            $PayeeBank = PayeeBank::where("payee_id", $payee->id)->first();
            if (!$PayeeBank) {
                $payeeBank = PayeeBank::create([
                    "user_id"            => $payee->user_id,
                    "unique_id"          => $payee->unique_id,
                    "payee_id"           => $payee->id,
                    "transaction_mode"   => null,
                    "account_holder_name" => $validated["account_name"],
                    "routing_no"         => $validated["routing_number"],
                    "account_no"         => $validated["account_number"],
                    "account_type"       => $validated["account_type"],
                ]);

                return response()->json([
                    "message" => "Bank account linked successfully",
                    "data"    => [
                        "id"                  => $payeeBank->id,
                        "payee_id"            => $payee->id,
                        "account_holder_name" => $validated['account_name'],
                        "routing_no"          => $validated['routing_number'],
                        "account_no"          => $validated['account_number'],
                        "account_type"        => $validated['account_type'],
                        "created_at"          => $payeeBank->created_at->format('m-d-Y'),
                        "updated_at"          => $payeeBank->updated_at->format('m-d-Y'),
                    ]
                ], 201);
            } else {
                $bank = new PayeeInternationalBank();
                $bank->user_id              = $userId;
                $bank->payee_id             = $payee->id;
                $bank->account_holder_name  = $validated['account_name'];
                $bank->routing_no           = $validated['routing_number'];
                $bank->account_no           = $validated['account_number'];
                $bank->account_type         = $validated['account_type'];
                $bank->bank_acc_name        = $validated['bank_acc_name'] ?? $nick_name ?? null;
                $bank->address1             = $validated['address_line1'] ?? null;
                $bank->address2             = $validated['address_line2'] ?? null;
                $bank->city                 = $validated['city'] ?? null;
                $bank->state                = $validated['state'] ?? null;
                $bank->zip                  = $validated['zip'] ?? null;
                $bank->country              = 'US';
                $bank->unique_id = substr(str_replace('-', '', \Illuminate\Support\Str::uuid()), 0, 8);
                $bank->save();
                $responseData = [
                    "id"                  => $bank->unique_id,
                    "payee_id"            => $bank->payee_id,
                    "account_holder_name" => $bank->account_holder_name,
                    "routing_no"          => $bank->routing_no,
                    "account_no"          => $bank->account_no,
                    "account_type"        => $bank->account_type,
                    "created_at"          => $bank->created_at->format('m-d-Y'),
                    "updated_at"          => $bank->updated_at->format('m-d-Y'),
                ];

                // ✅ Using ApiResponse for standardized response
                return ApiResponse::success($responseData, 201, "Bank account linked successfully");
            }
        } catch (\Exception $e) {
            \Log::error("Error in payeeBankAccount: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Something went wrong!',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
    /**
     * @OA\Get(
     *     path="/api/v1/banks",
     *     summary="List authenticated user's bank accounts with filters",
     *     tags={"Bank Accounts"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         description="Page number for pagination (default: 1)",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         description="Number of records per page (default: 10)",
     *         @OA\Schema(type="integer", example=10)
     *     ),
     *     @OA\Response(response=200, description="List of user's bank accounts", @OA\JsonContent(ref="#/components/schemas/GetAllBanksResponse")),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/Unauthorized")),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error",
     *         @OA\JsonContent(ref="#/components/schemas/InternalServerError")
     *     )
     * )
     */
    public function getAllBanks(Request $request)
    {
        $userId = Auth::id();

        $query = BankAccount::where("user_id", $userId);

        // 🔹 Pagination setup
        $perPage = (int) $request->get("per_page", 10);
        $page = (int) $request->get("page", 1);

        // 🔹 Base query
        $query = BankAccount::where("user_id", $userId);

        // Optional Filters
        if ($request->has("bank_name")) {
            $query->where("bank_name", "like", "%" . $request->bank_name . "%");
        }

        if ($request->has("account_type")) {
            $query->where("account_type", $request->account_type);
        }

        // 🔹 Paginate results
        $paginator = $query->orderByDesc("created_at")->paginate($perPage, ["*"], "page", $page);

        // Mask decrypted account numbers
        $bankAccounts = collect($paginator->items())->transform(function ($account) {
            try {
                if (!empty($account->account_no)) {
                    $len = strlen($account->account_no);
                    $account->account_no = str_repeat("*", max(0, $len - 4)) . substr($account->account_no, -4);
                }

                if (!empty($account->routing_no)) {
                    $len1 = strlen($account->routing_no);
                    $account->routing_no = str_repeat("*", max(0, $len1 - 4)) . substr($account->routing_no, -4);
                }
            } catch (\Exception $e) {
                Log::channel("api")->warning(
                    "Masking failed for bank account ID " . $account->id,
                    ["error" => $e->getMessage()]
                );
                $account->account_no = "****";
                $account->routing_no = "****";
            }

            // Hide unwanted columns
            $account->makeHidden([
                'bank1_address1',
                'bank1_address2',
                'bank1_city',
                'bank1_state',
                'bank1_zip',
                'created_at',
                'updated_at',
                'signature_name',
                'signature',
                'bank_fractional_number',
                'bank_check_no',
                'bank_nickname'
            ]);

            return $account;
        });


        // ✅ Return only data array
        return ApiResponse::success([
            "status" => "success",
            "data" => $bankAccounts,
            "pagination" => [
                "total" => $paginator->total(),
                "per_page" => $paginator->perPage(),
                "current_page" => $paginator->currentPage(),
                "last_page" => $paginator->lastPage(),
                "from" => $paginator->firstItem(),
                "to" => $paginator->lastItem(),
            ]
        ], 200);
    }


    /**
     * @OA\Get(
     *     path="/api/v1/banks/{id}",
     *     summary="Get a specific bank account by ID for the authenticated user",
     *     tags={"Bank Accounts"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Bank Account ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Bank account found"),
     *     @OA\Response(response=404, description="Bank account not found"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function getBankById($id)
    {
        $userId = Auth::id();

        $bank = BankAccount::where("user_id", $userId)
            ->where("id", $id)
            ->first();

        if (!$bank) {
            return response()->json(["error" => "Bank account not found"], 404);
        }

        // ✅ Account No Mask
        if (!empty($bank->account_no)) {
            $length = strlen($bank->account_no);

            if ($length > 4) {
                $bank->account_no = str_repeat("*", $length - 4) . substr($bank->account_no, -4);
            } else {
                $bank->account_no = str_repeat("*", $length);
            }
        }

        // ✅ Routing No Mask
        if (!empty($bank->routing_no)) {
            $length = strlen($bank->routing_no);

            if ($length > 4) {
                $bank->routing_no = str_repeat("*", $length - 4) . substr($bank->routing_no, -4);
            } else {
                $bank->routing_no = str_repeat("*", $length);
            }
        }

        // ✅ Hide unwanted columns
        $bank->makeHidden([
            'bank1_address1',
            'bank1_address2',
            'bank1_city',
            'bank1_state',
            'bank1_zip',
            'created_at',
            'updated_at',
            'signature_name',
            'signature',
            'bank_fractional_number',
            'bank_check_no',
            'bank_nickname'
        ]);

        return ApiResponse::success([
            "status" => "success",
            "data" => $bank
        ], 200);
    }



    /**
     * @OA\Post(
     *     path="/api/v1/banks",
     *     summary="Create a new bank account",
     *     tags={"Bank Accounts"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={
     *                 "name", "bank_account_type", "address_line1", "city", "state", "zip", "country",
     *                 "account_name", "routing_no", "account_no", "confirm_account_number", "account_type",
     *                 "bank_nickname", "bank_name", "bank_street_address", "bank_city", "bank_state", "bank_zip"
     *             },
     *             @OA\Property(property="name", type="string", example="customer bank"),
     *             @OA\Property(property="bank_account_type", type="string", example="savings"),
     *             @OA\Property(property="address_line1", type="string", example="demo,1232"),
     *             @OA\Property(property="address_line2", type="string", example="demo2,34322"),
     *             @OA\Property(property="phone", type="string", example="9876565744"),
     *             @OA\Property(property="city", type="string", example="demo_city"),
     *             @OA\Property(property="state", type="string", example="FL"),
     *             @OA\Property(property="zip", type="string", example="12345"),
     *             @OA\Property(property="country", type="string", example="US"),
     *             @OA\Property(property="account_name", type="string", example="Demo Tester"),
     *             @OA\Property(property="routing_no", type="string", example="555555550"),
     *             @OA\Property(property="account_no", type="string", example="123456789"),
     *             @OA\Property(property="confirm_account_number", type="string", example="123456789"),
     *             @OA\Property(property="account_type", type="string", enum={"savings", "checking", "business"}, example="savings"),
     *             @OA\Property(property="bank_nickname", type="string", example="Personal Savings"),
     *             @OA\Property(property="bank_name", type="string", example="Bank of America"),
     *             @OA\Property(property="bank_street_address", type="string", example="123 Main St"),
     *             @OA\Property(property="bank_city", type="string", example="Los Angeles"),
     *             @OA\Property(property="bank_state", type="string", example="CA"),
     *             @OA\Property(property="bank_zip", type="string", example="90001")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Bank account created successfully"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function createBank(Request $request)
    {
        $rules = [
            "bank_nickname" => "required|string|max:255",
            "name" => "required|string|max:255",
            "bank_account_type" => "required|string|max:50",
            "address_line1" => "required|string|max:255",
            "address_line2" => "nullable|string|max:255",
            "phone" => ["nullable", "string", 'regex:/^\+?[0-9]{10,15}$/'],
            "city" => "required|string|max:100",
            "state" => "required|string|max:100",
            "zip" => "required|string|max:20",
            "country" => "required|string|size:2",
            "account_name" => "required|string|max:255",
            "routing_no" => "required|digits:9",
            "account_no" => "required|numeric|digits_between:6,20",
            "confirm_account_number" => "required|same:account_no",
            "account_type" => "required|string|in:savings,checking,business",
            "bank_name" => "required|string|max:255",
            "bank_street_address" => "required|string|max:255",
            "bank_city" => "required|string|max:100",
            "bank_state" => "required|string|max:100",
            "bank_zip" => "required|string|max:20",
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json(["errors" => $validator->errors()], 422);
        }

        $user = Auth::user();

        // Step 1: Save immediately
        $bank = BankAccount::create([
            'user_id'              => $user->id,
            'bank_nickname'        => $request->bank_nickname,
            'name'                 => $request->name,
            'bank_account_type'    => $request->bank_account_type,
            'bank1_address1'       => $request->address_line1,
            'bank1_address2'       => $request->address_line2,
            'phone'                => $request->phone,
            'bank1_city'           => $request->city,
            'bank1_state'          => $request->state,
            'bank1_zip'            => $request->zip,
            'country'              => $request->country,
            'account_name'         => $request->account_name,
            'routing_no'           => $request->routing_no,
            'account_no'           => $request->account_no,
            'account_type'         => $request->account_type,
            'bank_name'            => $request->bank_name,
            'bank_street_address'  => $request->bank_street_address,
            'bank_city'            => $request->bank_city,
            'bank_state'           => $request->bank_state,
            'bank_zip'             => $request->bank_zip,
            'status'               => 'pending_verification', // ✅ add status field
        ]);

        // Step 2: Dispatch job just for verification
        VerifyAndCreateBankAccount::dispatch($bank, $user);

        // Step 3: Return success with bank_id
        return ApiResponse::success([
            "message" => "Bank created successfully. Verification in progress.",
            "bank_id" => $bank->id
        ], 200);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/recurring-payments",
     *     summary="List all recurring payment schedules",
     *     tags={"Recurring Payments"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         description="Page number for pagination (default: 1)",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         description="Number of records per page (default: 10)",
     *         @OA\Schema(type="integer", example=10)
     *     ),
     *     @OA\Response(response=200, description="List of recurring payments", @OA\JsonContent(ref="#/components/schemas/RecurringPaymentListResponse")),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/Unauthorized"))
     * )
     */ public function RecurringPayments(Request $request)
    {
        try {
            $user = Auth::user();

            if (!$user) {
                throw new \Exception('User not authenticated');
            }

            // 🔹 Pagination setup
            $perPage = (int) $request->get("per_page", 10);
            $page = (int) $request->get("page", 1);

            // 🔹 Base Query with eager loads
            $query = RecurringInformation::where("user_id", $user->id)
                ->with(["payerRelation", "payableToRelation"])
                ->orderByDesc("created_at");

            // 🔹 Paginate the results
            $paginator = $query->paginate($perPage, ["*"], "page", $page);

            // Fetch all recurring payment info for the user, eager loading relations if defined
            $recurrings = collect($paginator->items())->map(function ($recurring) {
                try {
                    $payer = null;
                    $payerId = null;

                    // Check if payer is from International Bank (ibank_xxxx format)
                    if (is_string($recurring->payer) && str_starts_with($recurring->payer, 'ibank_')) {
                        $ibankId = (int) str_replace('ibank_', '', $recurring->payer);

                        $payeeBank = PayeeInternationalBank::where("id", $ibankId)->first();
                        if ($payeeBank) {
                            $payer = $payeeBank->bank_acc_name ?? null;
                            $payerId =  $payeeBank->unique_id;
                        }
                    } else {
                        // Normal PayeeBank
                        $payeeBank = PayeeBank::find($recurring->payer);
                        if ($payeeBank) {
                            $payer = $payeeBank->account_holder_name ?? null;
                            $payerId = $payeeBank->id;
                        }
                    }

                    // PayableTo (as you had before)
                    $payableTo = $recurring->payableToRelation ?? BankAccount::find($recurring->payable_to);
                    $parts = explode('-', $recurring->payment_processed, 2);

                    // Default values
                    $scheduleName = null;
                    $schedulePurpose = null;
                    $scheduleName = $parts[0] ?? null;
                    $schedulePurpose = $parts[1] ?? null;

                    return [
                        "id" => $recurring->id,
                        "status" => $recurring->status,
                        "user_id" => $recurring->user_id,
                        "recurring" => $recurring->recurring,
                        "first_payment_date" => $recurring->first_payment_date ? \Carbon\Carbon::parse($recurring->first_payment_date)->format('m-d-Y') : null,
                        "last_bill_date" => $recurring->last_bill_date ? \Carbon\Carbon::parse($recurring->last_bill_date)->format('m-d-Y') : null,
                        "number_of_payments" => $recurring->number_of_payments,
                        "amount" => $recurring->amount,
                        "payer" => $payer,
                        "payable_to" => $payableTo?->name ?? null,
                        "payer_id" => $payerId,
                        "payable_to_id" => $payableTo?->id ?? null,
                        "schedule_name" => $scheduleName,
                        "schedule_purpose" => $schedulePurpose,
                        "count_payments" => $recurring->count_payments,
                        "next_bill_date" => $recurring->next_bill_date ? \Carbon\Carbon::parse($recurring->next_bill_date)->format('m-d-Y') : null,
                        "created_at" => $recurring->created_at ? \Carbon\Carbon::parse($recurring->created_at)->format('m-d-Y') : null,
                        "updated_at" => $recurring->updated_at ? \Carbon\Carbon::parse($recurring->updated_at)->format('m-d-Y') : null,
                    ];
                } catch (\Exception $e) {
                    \Log::error('Error processing recurring payment: ' . $e->getMessage());
                    return null;
                }
            })->filter()->values();


            return ApiResponse::success([
                "status" => "success",
                "data" => $recurrings,
                "pagination" => [
                    "total" => $paginator->total(),
                    "per_page" => $paginator->perPage(),
                    "current_page" => $paginator->currentPage(),
                    "last_page" => $paginator->lastPage(),
                    "from" => $paginator->firstItem(),
                    "to" => $paginator->lastItem(),
                ]
            ], 200);
        } catch (\Exception $e) {
            // Log the error for debugging
            \Log::error('Error in RecurringPayments: ' . $e->getMessage());

            return response()->json([
                "status" => "error",
                "message" => "An error occurred while fetching recurring payments",
                "error" => $e->getMessage() // Optionally include error message for debugging (remove in production)
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/recurring-payments/{id}",
     *     summary="Get a specific recurring payment",
     *     tags={"Recurring Payments"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the recurring payment",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Recurring payment details",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="status", type="string", example="active"),
     *                 @OA\Property(property="user_id", type="integer"),
     *                 @OA\Property(property="recurring", type="string"),
     *                 @OA\Property(property="first_payment_date", type="string", format="date"),
     *                 @OA\Property(property="payer", type="integer", example=2),
     *                 @OA\Property(property="payable_to", type="integer", example=3),
     *                 @OA\Property(property="amount", type="string"),
     *                 @OA\Property(property="next_bill_date", type="string", format="date")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Recurring payment not found.")
     *         )
     *     )
     * )
     */ public function GetRecurringData($id)
    {
        try {
            $user = Auth::user();

            if (!$user) {
                throw new \Exception('User not authenticated');
            }

            $recurring = RecurringInformation::where("user_id", $user->id)
                ->where("id", $id)
                ->first();

            if (!$recurring) {
                return response()->json(
                    [
                        "status" => "error",
                        "message" => "Recurring payment not found.",
                    ],
                    404
                );
            }

            // Use Eloquent relationships if defined; otherwise fallback to manual queries
            // $payer = $recurring->payerRelation ?? Payee::find($recurring->payer);
            // $payableTo = $recurring->payableToRelation ?? BankAccount::find($recurring->payable_to);
            $payer = null;
            $payerId = null;

            // Check if payer is from International Bank (ibank_xxxx format)
            if (is_string($recurring->payer) && str_starts_with($recurring->payer, 'ibank_')) {
                $ibankId = (int) str_replace('ibank_', '', $recurring->payer);

                $payeeBank = PayeeInternationalBank::where("id", $ibankId)->first();
                if ($payeeBank) {
                    $payer = $payeeBank->bank_acc_name ?? null;
                    $payerId =  $payeeBank->unique_id;
                }
            } else {
                // Normal PayeeBank
                $payeeBank = PayeeBank::find($recurring->payer);
                if ($payeeBank) {
                    $payer = $payeeBank->account_holder_name ?? null;
                    $payerId = $payeeBank->id;
                }
            }

            // $payeeBank = PayeeBank::find($recurring->payer);
            // Fallback if relationships are not loaded or not defined
            $payer = $recurring->payerRelation ?? Payee::find($payeeBank->payee_id);
            $payableTo = $recurring->payableToRelation ?? BankAccount::find($recurring->payable_to);
            $responseData = [
                "id" => $recurring->id,
                "status" => $recurring->status,
                "user_id" => $recurring->user_id,
                "recurring" => $recurring->recurring,
                "first_payment_date" => $recurring->first_payment_date ? \Carbon\Carbon::parse($recurring->first_payment_date)->format('m-d-Y') : null,
                "last_bill_date" => $recurring->last_bill_date ? \Carbon\Carbon::parse($recurring->last_bill_date)->format('m-d-Y') : null,
                "number_of_payments" => $recurring->number_of_payments,
                "amount" => $recurring->amount,
                "payable_to" => $payableTo?->name ?? null,
                "payer_id" => $payeeBank?->unique_id ?? null,
                "payable_to_id" => $payableTo?->id ?? null,
                "schedule_purpose" => $recurring->purpose,
                "count_payments" => $recurring->count_payments,
                "transaction_status" => $recurring->transaction_status,
                "next_bill_date" => $recurring->next_bill_date ? \Carbon\Carbon::parse($recurring->next_bill_date)->format('m-d-Y') : null,
                "created_at" => $recurring->created_at ? \Carbon\Carbon::parse($recurring->created_at)->format('m-d-Y') : null,
                "updated_at" => $recurring->updated_at ? \Carbon\Carbon::parse($recurring->updated_at)->format('m-d-Y') : null,
            ];
            return ApiResponse::success([
                "status" => "success",
                "data" => $responseData
            ], 200);
        } catch (\Exception $e) {
            // Log the error for debugging
            \Log::error('Error in GetRecurringData: ' . $e->getMessage());

            return response()->json([
                "status" => "error",
                "message" => "An error occurred while fetching recurring payment data",
                "error" => $e->getMessage() // Optionally include error message for debugging (remove in production)
            ], 500);
        }
    }
    /**
     * @OA\Post(
     *     path="/api/v1/recurring-payments",
     *     summary="Create a new recurring payment schedule",
     *     tags={"Recurring Payments"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={
     *                 "status", 
     *                 "recurring", 
     *                 "first_payment_date", 
     *                 "number_of_payments", 
     *                 "last_bill_date", 
     *                 "amount", 
     *                 "payer", 
     *                 "payable_to"
     *             },
     *             @OA\Property(
     *                 property="status", 
     *                 type="string", 
     *                 enum={"Active","Pause","active","pause"}, 
     *                 example="Active"
     *             ),
     *             @OA\Property(
     *                 property="recurring", 
     *                 type="string", 
     *                 enum={
     *                     "Monthly","Bi-weekly","Bi-Weekly","bi-weekly","bi-Weekly",
     *                     "monthly","yearly","Daily","daily",
     *                     "Once-A-Week","Once-a-Week","once-A-Week","Once-A-week","once-A-Week","Once-a-week"
     *                 }, 
     *                 example="Monthly"
     *             ),
     *             @OA\Property(property="first_payment_date", type="string", format="date", example="2025-09-19"),
     *             @OA\Property(property="number_of_payments", type="integer", minimum=1, example=12),
     *             @OA\Property(property="last_bill_date", type="string", format="date", example="2025-09-19"),
     *             @OA\Property(property="amount", type="number", format="float", minimum=1.00, example=100.00),
     *             @OA\Property(property="purpose", type="string", nullable=true, example="Purpose of payment"),
     *             @OA\Property(property="payer", type="integer", example=1, description="Payee ID, must exist in payees table"),
     *             @OA\Property(property="payable_to", type="integer", example=10, description="Bank account ID, must exist in bank_accounts table")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Recurring payment schedule created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Recurring payment schedule created successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="status", type="string", example="Active"),
     *                 @OA\Property(property="recurring", type="string", example="Monthly"),
     *                 @OA\Property(property="first_payment_date", type="string", format="date", example="2025-09-19")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Validation errors")
     *         )
     *     )
     * )
     */

    public function createRecurringPayment(Request $request)
    {
        $rules = [
            "status" => "required|string|in:Active,Pause,active,pause",
            "recurring" =>
            "required|string|in:Monthly,Bi-weekly,Bi-Weekly,bi-weekly,bi-Weekly,monthly,yearly,Daily,daily,Once-A-Week,Once-a-Week,once-A-Week,Once-A-week,once-A-Week,Once-a-week",
            "first_payment_date" => "required|date_format:Y-m-d",
            "number_of_payments" => "required|integer|min:1",
            "amount" => "required|numeric|min:1.00",
            "schedule_name" => "nullable|string",
            "schedule_purpose" => "nullable|string",
            "payer" => "required|regex:/^[0-9a-zA-Z]+$/",
            "payable_to" => "required|integer",
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json(
                [
                    "status" => "error",
                    "message" => $validator->errors()->first(),
                    "errors" => $validator->errors(),
                ],
                422
            );
        }

        $user = Auth::user();

        try {
            $bankAccountExists = BankAccount::where("id", $request->payable_to)
                ->where("user_id", $user->id)
                ->exists();

            if (!$bankAccountExists) {
                return response()->json([
                    "status"  => "error",
                    "message" => "Invalid payable account (not found)",
                ], 422);
            }

            // ✅ Check if payer exists
            $payerExistsInBank = PayeeBank::where("id", $request->payer)
                ->where("user_id", $user->id)
                ->exists();

            if ($payerExistsInBank) {
                $PayeeBankdataid = $request->payer;
            } else {
                $PayeeBankdata = PayeeInternationalBank::where("unique_id", $request->payer)
                    ->where("user_id", $user->id)
                    ->first();

                if ($PayeeBankdata) {
                    $PayeeBankdataid = 'ibank_' . $PayeeBankdata->id;
                } else {
                    return response()->json([
                        "status"  => "error",
                        "message" => "Invalid payer (not found in Payee Bank or Additional Bank)",
                    ], 422);
                }
            }

            $scheduleName = $request->schedule_name;
            $schedulePurpose = $request->schedule_purpose;
            $purpose = $scheduleName . ' - ' . $schedulePurpose;

            DB::beginTransaction();

            // ✅ Normalize recurring type
            $recurringType = ucfirst(strtolower($request->recurring));
            if (in_array(strtolower($request->recurring), ['bi-weekly', 'biweekly'])) {
                $recurringType = "Bi-Weekly";
            } elseif (in_array(strtolower($request->recurring), ['once-a-week', 'onceaweek'])) {
                $recurringType = "Once-a-Week";
            } elseif (strtolower($request->recurring) === 'yearly') {
                $recurringType = "Yearly";
            }

            // ✅ Payment date calculation logic
            $paymentDates = [];
            $lastBillDate = null;

            if (!empty($request->first_payment_date) && !empty($request->number_of_payments)) {
                $startDate = \Carbon\Carbon::parse($request->first_payment_date);
                $count = 0;

                while ($count < $request->number_of_payments) {
                    if (!$startDate->isWeekend()) {
                        $paymentDates[] = $startDate->format('Y-m-d');
                        $count++;
                    }

                    // increment based on recurring type
                    switch ($recurringType) {
                        case "Daily":
                            $startDate->addDay();
                            break;
                        case "Once-a-Week":
                            $startDate->addWeek();
                            break;
                        case "Bi-Weekly":
                            $startDate->addWeeks(2);
                            break;
                        case "Monthly":
                            $startDate->addMonthNoOverflow(); // handles 30/31 issue
                            break;
                        case "Yearly":
                            $startDate->addYear();
                            break;
                        default:
                            $startDate->addDay();
                            break;
                    }
                }

                $lastBillDate = end($paymentDates);
            }

            $firstPaymentDate = Carbon::parse($request->first_payment_date);

            // Check if Saturday (6) or Sunday (0)
            if ($firstPaymentDate->isSaturday()) {
                $nextBillDate = $firstPaymentDate->addDays(2); // move to Monday
            } elseif ($firstPaymentDate->isSunday()) {
                $nextBillDate = $firstPaymentDate->addDay(); // move to Monday
            } else {
                $nextBillDate = $firstPaymentDate;
            }

            $recurringPayment = RecurringInformation::create([
                "user_id" => $user->id,
                "status" => ucfirst(strtolower($request->status)),
                "recurring" => $recurringType,
                "first_payment_date" => $request->first_payment_date,
                "number_of_payments" => $request->number_of_payments,
                "last_bill_date" => $lastBillDate,
                "amount" => $request->amount,
                "payer" => $PayeeBankdataid,
                "payable_to" => $request->payable_to,
                "next_bill_date" => $nextBillDate->format('Y-m-d'),
                "payment_processed" => $purpose,
                "purpose" => $purpose,
                "payment_dates" => !empty($paymentDates) ? json_encode($paymentDates) : null,
            ]);

            DB::commit();

            // format response
            $formattedPayment = $recurringPayment->toArray();
            $formattedPayment['first_payment_date'] = \Carbon\Carbon::parse($recurringPayment->first_payment_date)->format('m-d-Y');
            $formattedPayment['last_bill_date'] = \Carbon\Carbon::parse($recurringPayment->last_bill_date)->format('m-d-Y');
            $formattedPayment['updated_at'] = \Carbon\Carbon::parse($recurringPayment->updated_at)->format('m-d-Y');
            $formattedPayment['created_at'] = \Carbon\Carbon::parse($recurringPayment->created_at)->format('m-d-Y');

            Log::info("Recurring payment schedule created", [
                "id" => $recurringPayment->id,
                "user_id" => $user->id,
            ]);

            return response()->json(
                [
                    "status" => "success",
                    "message" => "Recurring payment schedule created successfully",
                    "data" => $formattedPayment
                ],
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error("Failed to create recurring payment", [
                "error" => $e->getMessage(),
                "user_id" => $user->id,
            ]);

            return response()->json(
                [
                    "status" => "error",
                    "message" => "Something went wrong while creating recurring payment schedule",
                ],
                500
            );
        }
    }
    /**
     * @OA\Patch(
     *     path="/api/v1/recurring-payments/{id}",
     *     summary="Update a recurring payment (partial update)",
     *     tags={"Recurring Payments"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the recurring payment to update",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 type="object",
     *                 @OA\Property(property="recurring", type="string", example="yearly"),
     *                 @OA\Property(property="first_payment_date", type="string", format="date", example="2024-12-10"),
     *                 @OA\Property(property="number_of_payments", type="integer", example=12),
     *                 @OA\Property(property="last_bill_date", type="string", format="date", example="2024-11-10"),
     *                 @OA\Property(property="amount", type="number", format="float", example=100.00),
     *                 @OA\Property(property="payer", type="integer", example=25),
     *                 @OA\Property(property="payable_to", type="integer", example=29),
     *                 @OA\Property(property="schedule_name", type="string", example="my-bank"),
     *                 @OA\Property(property="schedule_purpose", type="string", example="service payment")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Recurring payment updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Recurring payment updated successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="recurring", type="string", example="yearly"),
     *                 @OA\Property(property="first_payment_date", type="string", format="date", example="2024-12-10"),
     *                 @OA\Property(property="number_of_payments", type="integer", example=12),
     *                 @OA\Property(property="last_bill_date", type="string", format="date", example="2024-11-10"),
     *                 @OA\Property(property="amount", type="number", format="float", example=100.00),
     *                 @OA\Property(property="payment_processed", type="string", example="processed"),
     *                 @OA\Property(property="payer", type="integer", example=25),
     *                 @OA\Property(property="payable_to", type="integer", example=29),
     *                 @OA\Property(property="memo", type="string", example="my-bank"),
     *                 @OA\Property(property="purpose", type="string", example="service payment"),
     *                 @OA\Property(property="count_payments", type="integer", example=12),
     *                 @OA\Property(property="transaction_status", type="integer", example=1),
     *                 @OA\Property(property="next_bill_date", type="string", format="date", example="2025-01-01")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Recurring payment not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Recurring payment not found.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Validation error: payer not found.")
     *         )
     *     )
     * )
     */
    public function updateRecurringPayment(Request $request, $id)
    {
        $rules = [
            "recurring" => "nullable|string|in:Monthly,Bi-weekly,Bi-Weekly,bi-weekly,bi-Weekly,monthly,yearly,Daily,daily,Once-A-Week,Once-a-Week,once-A-Week,Once-A-week,once-A-Week,Once-a-week",
            "first_payment_date" => "nullable|date_format:Y-m-d",
            "number_of_payments" => "nullable|integer|min:1",
            "amount" => "nullable|numeric|min:0.01",
            "payer" => "nullable|regex:/^[0-9a-zA-Z]+$/",
            "payable_to" => "nullable|integer|exists:bank_accounts,id",
            "schedule_name" => "nullable|string",
            "schedule_purpose" => "nullable|string",
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                "status" => "error",
                "message" => $validator->errors()->first(),
                "errors" => $validator->errors(),
            ], 422);
        }

        $user = Auth::user();

        $recurringPayment = RecurringInformation::where("id", $id)
            ->where("user_id", $user->id)
            ->first();

        if (!$recurringPayment) {
            return response()->json([
                "status" => "error",
                "message" => "Recurring payment not found.",
            ], 404);
        }

        try {
            DB::beginTransaction();

            $updateData = $request->only(array_keys($rules));

            if (isset($updateData["recurring"])) {
                $updateData["recurring"] = ucfirst(strtolower($updateData["recurring"]));
            }

            if (isset($updateData["schedule_purpose"])) {
                $updateData["purpose"] = ($updateData["schedule_name"] ?? '') . '-' . $updateData["schedule_purpose"];
                unset($updateData["schedule_purpose"], $updateData["schedule_name"]);
            }

            // ✅ Generate Payment Dates based on recurring type
            $paymentDates = [];
            $lastBillDate = null;

            if (!empty($updateData['first_payment_date']) && !empty($updateData['number_of_payments'])) {
                $startDate = Carbon::parse($updateData['first_payment_date']);
                $count = 0;

                while ($count < $updateData['number_of_payments']) {
                    switch (strtolower($updateData['recurring'])) {
                        case 'monthly':
                            $paymentDates[] = $startDate->format('Y-m-d');
                            $startDate->addMonth();
                            break;

                        case 'bi-weekly':
                            $paymentDates[] = $startDate->format('Y-m-d');
                            $startDate->addWeeks(2);
                            break;

                        case 'yearly':
                            $paymentDates[] = $startDate->format('Y-m-d');
                            $startDate->addYear();
                            break;

                        case 'daily':
                            if (!$startDate->isWeekend()) { // weekends skip
                                $paymentDates[] = $startDate->format('Y-m-d');
                            }
                            $startDate->addDay();
                            break;

                        case 'once-a-week':
                            $paymentDates[] = $startDate->format('Y-m-d');
                            $startDate->addWeek();
                            break;

                        default:
                            $paymentDates[] = $startDate->format('Y-m-d');
                            $startDate->addDay();
                            break;
                    }

                    $count++;
                }

                $lastBillDate = end($paymentDates);
                $updateData['payment_dates'] = json_encode($paymentDates);
                $updateData['last_bill_date'] = $lastBillDate;
            }

            $recurringPayment->update($updateData);

            DB::commit();

            Log::info("Recurring payment updated", [
                "id" => $recurringPayment->id,
                "user_id" => $user->id,
            ]);

            // ✅ Format output
            $formattedPayment = $recurringPayment
                ->makeHidden(['same_day_ach', 'last_error_message', 'last_attempt_at', 'failure_count', 'transaction_status', 'payment_processed', 'final_amount'])
                ->toArray();

            $scheduleName = null;
            $schedulePurpose = null;
            if (!empty($formattedPayment['purpose'])) {
                $parts = explode('-', $formattedPayment['purpose'], 2);
                $scheduleName = isset($parts[0]) ? trim($parts[0]) : null;
                $schedulePurpose = isset($parts[1]) ? trim($parts[1]) : null;
            }
            unset($formattedPayment['purpose']);

            $formattedPayment['schedule_name']    = $scheduleName;
            $formattedPayment['schedule_purpose'] = $schedulePurpose;
            $formattedPayment['first_payment_date'] = $recurringPayment->first_payment_date ? Carbon::parse($recurringPayment->first_payment_date)->format('m-d-Y') : null;
            $formattedPayment['last_bill_date'] = $recurringPayment->last_bill_date ? Carbon::parse($recurringPayment->last_bill_date)->format('m-d-Y') : null;
            $formattedPayment['updated_at'] = Carbon::parse($recurringPayment->updated_at)->format('m-d-Y');
            $formattedPayment['created_at'] = Carbon::parse($recurringPayment->created_at)->format('m-d-Y');

            return ApiResponse::success([
                "data" => $formattedPayment
            ], "Recurring payment updated successfully", 200);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error("Error updating recurring payment", [
                "id" => $id,
                "user_id" => $user->id,
                "error" => $e->getMessage(),
            ]);

            return response()->json([
                "status" => "error",
                "message" => "Something went wrong while updating the recurring payment",
            ], 500);
        }
    }


    /**
     * @OA\Post(
     *     path="/api/v1/recurring-payments/{id}/{status}",
     *     summary="Update the status of a recurring payment",
     *     tags={"Recurring Payments"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the recurring payment",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="path",
     *         required=true,
     *         description="The new status of the recurring payment (pause, resume, stop)",
     *         @OA\Schema(type="string", enum={"pause", "resume", "stop"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Status updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Recurring payment status updated successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid status or payment ID",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Invalid status or payment ID")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Recurring payment not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Recurring payment not found.")
     *         )
     *     )
     * )
     */ public function updateRecurringPaymentStatus($id, $status)
    {
        $status = strtolower($status);
        $validStatuses = [
            "pause" => "Paused",
            "resume" => "Active",
            "stop" => "Stopped",
        ];

        if (!array_key_exists($status, $validStatuses)) {
            return response()->json(
                [
                    "status" => "error",
                    "message" =>
                    "Invalid status provided. Allowed values are: pause, resume, stop.",
                ],
                400
            );
        }

        $recurring = RecurringInformation::find($id);

        if (!$recurring) {
            return response()->json(
                [
                    "status" => "error",
                    "message" => "Recurring payment not found.",
                ],
                404
            );
        }

        $currentStatus = strtolower($recurring->status);

        if ($currentStatus === "stopped") {
            return response()->json(
                [
                    "status" => "error",
                    "message" =>
                    "Status cannot be changed. The recurring payment is already stopped.",
                ],
                400
            );
        }

        if ($currentStatus === strtolower($validStatuses[$status])) {
            return response()->json(
                [
                    "status" => "error",
                    "message" =>
                    "Recurring payment is already in the desired status.",
                ],
                400
            );
        }

        try {
            DB::beginTransaction();

            $recurring->status = $validStatuses[$status];
            $recurring->save();

            DB::commit();

            Log::info("Recurring payment status updated", [
                "id" => $recurring->id,
                "new_status" => $recurring->status,
            ]);

            return ApiResponse::success([
                "id" => $recurring->id,
                "status" => $recurring->status,
            ], "Recurring payment status updated successfully.", 200);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error("Failed to update recurring payment status", [
                "id" => $id,
                "error" => $e->getMessage(),
            ]);

            return response()->json(
                [
                    "status" => "error",
                    "message" => "Failed to update recurring payment status.",
                ],
                500
            );
        }
    }
}
