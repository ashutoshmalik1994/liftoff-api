<?php

namespace App\Schema\V1;

use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="TransactionListResponse",
 *     type="object",
 *     description="Response schema for listing transactions",
 *     @OA\Property(
 *         property="status",
 *         type="integer",
 *         example=200
 *     ),
 *     @OA\Property(
 *         property="message",
 *         type="string",
 *         example="Transactions fetched successfully"
 *     ),
 *     @OA\Property(
 *         property="data",
 *         type="object",
 *         @OA\Property(
 *             property="transaction_id",
 *             type="string",
 *             example="81383c5c-ee32-42fd-8578-4dc4bbfd11e1"
 *         ),
 *         @OA\Property(
 *             property="user",
 *             type="object",
 *             @OA\Property(property="id", type="integer", example=25),
 *             @OA\Property(property="name", type="string", example="loreum"),
 *             @OA\Property(property="email", type="string", example="kaswebtester@gmail.com")
 *         ),
 *         @OA\Property(
 *             property="transactions",
 *             type="array",
 *             @OA\Items(ref="#/components/schemas/TransactionAll")
 *         ),
 *         @OA\Property(
 *             property="pagination",
 *             ref="#/components/schemas/pagination"
 *         )
 *     )
 * )
 * 
 * @OA\Schema(
 *     schema="TransactionSchemaBase",
 *     type="object",
 *     description="Schema related to transaction having common fields.",
 *     @OA\Property(
 *         property="id",
 *         type="integer",
 *         readOnly=true,
 *         description="Unique transaction ID.",
 *         example=187
 *     ),
 *     @OA\Property(
 *         property="payee_id",
 *         type="integer",
 *         description="ID of the payee.",
 *         example=22
 *     ),
 *     @OA\Property(
 *         property="debit_card_id",
 *         type="integer",
 *         nullable=true,
 *         description="Linked debit card ID, if any.",
 *         example=null
 *     ),
 *     @OA\Property(
 *         property="bank_id",
 *         type="integer",
 *         description="ID of the bank account used.",
 *         example=37
 *     ),
 *     @OA\Property(
 *         property="wallet_id",
 *         type="integer",
 *         nullable=true,
 *         description="Linked wallet ID, if any.",
 *         example=null
 *     ),
 *     @OA\Property(
 *         property="payee_account_no",
 *         type="string",
 *         description="Masked account number of the payee.",
 *         example="XXXXXXX2121"
 *     ),
 *     @OA\Property(
 *         property="email",
 *         type="string",
 *         format="email",
 *         description="Email of the payee.",
 *         example="test@gmail.com"
 *     ),
 *     @OA\Property(
 *         property="payee_cat",
 *         type="string",
 *         nullable=true,
 *         description="Payee category.",
 *         example=null
 *     ),
 *     @OA\Property(
 *         property="amount",
 *         type="string",
 *         description="Transaction amount.",
 *         example="21.00"
 *     ),
 *     @OA\Property(
 *         property="payment_date",
 *         type="string",
 *         format="date",
 *         description="Scheduled date of the payment.",
 *         example="2025-04-02"
 *     ),
 *     @OA\Property(
 *         property="payee_id_acc",
 *         type="string",
 *         nullable=true,
 *         description="Account ID of the payee.",
 *         example=null
 *     ),
 *     @OA\Property(
 *         property="transfer_mode",
 *         type="string",
 *         description="Transfer type.",
 *         example="Recurring"
 *     ),
 *     @OA\Property(
 *         property="rtp",
 *         type="string",
 *         nullable=true,
 *         description="RTP flag.",
 *         example=null
 *     ),
 *     @OA\Property(
 *         property="settlement_date",
 *         type="string",
 *         format="date",
 *         nullable=true,
 *         description="Settlement date of the transaction.",
 *         example=null
 *     ),
 *     @OA\Property(
 *         property="rtn_code",
 *         type="string",
 *         nullable=true,
 *         description="Return code if transaction failed.",
 *         example=null
 *     )
 * )
 * 
 * @OA\Schema(
 *     schema="TransactionAll",
 *     description="Schema related to getAllTransaction",
 *     allOf={
 *         @OA\Schema(ref="#/components/schemas/TransactionSchemaBase"),
 *         @OA\Schema(
 *             type="object",
 *             @OA\Property(
 *                 property="type",
 *                 type="string",
 *                 nullable=true,
 *                 description="Transaction type.",
 *                 example=null
 *             ),
 *             @OA\Property(
 *                 property="user_id",
 *                 type="integer",
 *                 description="User ID.",
 *                 example=29
 *             ),
 *             @OA\Property(
 *                 property="invoice_no",
 *                 type="string",
 *                 description="Invoice number.",
 *                 example="INV23232YESS_12"
 *             ),
 *             @OA\Property(
 *                 property="trans_comment",
 *                 type="string",
 *                 nullable=true,
 *                 description="Transaction comment if any.",
 *                 example=null
 *             ),
 *             @OA\Property(
 *                 property="confirmation",
 *                 type="string",
 *                 nullable=true,
 *                 description="Confirmation if transaction is successful.",
 *                 example="240712021905968TEST"
 *             ),
 *             @OA\Property(
 *                 property="tokenize_payment",
 *                 type="string",
 *                 nullable=true,
 *                 example=null
 *             ),
 *             @OA\Property(
 *                 property="status_text",
 *                 type="string",
 *                 description="Status of a transaction.",
 *                 example="Cleared"
 *             ),
 *             @OA\Property(
 *                 property="merchant_payee_name",
 *                 type="string",
 *                 description="Merchant or Payee Name.",
 *                 nullable=true,
 *                 example=null
 *             ),
 *             @OA\Property(
 *                 property="schedule_name",
 *                 type="string",
 *                 example="sfvdzfvsd"
 *             ),
 *             @OA\Property(
 *                 property="schedule_purpose",
 *                 type="string",
 *                 example=""
 *             ),
 *             @OA\Property(
 *                 property="transfer_mode",
 *                 type="string",
 *                 nullable=true,
 *                 example="ACH / Direct Deposit"
 *             ),
 *             @OA\Property(
 *                 property="return_date",
 *                 type="string",
 *                 format="date",
 *                 nullable=true,
 *                 example=null
 *             ),
 *             @OA\Property(
 *                 property="deposit_date",
 *                 type="string",
 *                 format="date",
 *                 nullable=true,
 *                 example="07-15-2024"
 *             ),
 *             @OA\Property(
 *                 property="organization_date",
 *                 type="string",
 *                 format="date",
 *                 nullable=true,
 *                 example="07-15-2024"
 *             ),
 *             @OA\Property(
 *                 property="effective_date",
 *                 type="string",
 *                 format="date",
 *                 nullable=true,
 *                 example="07-15-2024"
 *             )
 *         )
 *     }
 * )
 * 
 * @OA\Schema(
 *     schema="pagination",
 *     type="object",
 *     description="Schema related to pagination",
 *     @OA\Property(
 *         property="total",
 *         type="integer",
 *         example=46
 *     ),
 *     @OA\Property(
 *         property="per_page",
 *         type="integer",
 *         example=3
 *     ),
 *     @OA\Property(
 *         property="current_page",
 *         type="integer",
 *         example=2
 *     ),
 *     @OA\Property(
 *         property="last_page",
 *         type="integer",
 *         example=16
 *     ),
 *     @OA\Property(
 *         property="from",
 *         type="integer",
 *         example=4
 *     ),
 *     @OA\Property(
 *         property="to",
 *         type="integer",
 *         example=6
 *     )
 * )
 * 
 *  @OA\Schema(
 *     schema="TransactionByConfirmationID",
 *     type="object",
 *     description="Response schema for transaction or receivable by confirmation id",
 *     @OA\Property(
 *         property="status",
 *         type="integer",
 *         example=200
 *     ),
 *     @OA\Property(
 *         property="message",
 *         type="string",
 *         example="Success"
 *     ),
 *     @OA\Property(
 *         property="data",
 *         type="object",
 *         @OA\Property(
 *             property="transaction_id",
 *             type="string",
 *             example="81383c5c-ee32-42fd-8578-4dc4bbfd11e1"
 *         ),
 *         @OA\Property(
 *             property="source",
 *             type="string",
 *             example="transactions"
 *         ),
 *         @OA\Property(
 *             property="transaction",
 *             ref="#/components/schemas/TransactionByConfirmationMergedSchema"
 *         )
 *     )
 * )
 * @OA\Schema(
 *     schema="TransactionByConfirmationMergedSchema",
 *     allOf={
 *         @OA\Schema(ref="#/components/schemas/TransactionCommonSchema"),
 *         @OA\Schema(ref="#/components/schemas/TransactionByConfirmationIdBaseSchema")
 *     }
 * )
 * 
 * @OA\Schema(
 *     schema="TransactionByConfirmationIdBaseSchema",
 *     type="object",
 *     description="Transaction schema related to confirmation id",
 *     @OA\Property(
 *         property="rtn_date",
 *         type="string",
 *         format="date",
 *         nullable=true,
 *         example=null
 *     ),
 *     @OA\Property(
 *         property="start_date",
 *         type="string",
 *         format="date",
 *         nullable=true,
 *         example=null
 *     ),
 *     @OA\Property(
 *         property="organization_date",
 *         type="string",
 *         format="date",
 *         nullable=true,
 *         example="07-15-2024"
 *     ),
 *     @OA\Property(
 *         property="end_date",
 *         type="string",
 *         format="date",
 *         nullable=true,
 *         example=null
 *     )
 * )
 * 
 * @OA\Schema(
 *     schema="TransactionCommonSchema",
 *     description="Common Transaction schema",
 *     allOf={
 *         @OA\Schema(ref="#/components/schemas/TransactionSchemaBase"),
 *         @OA\Schema(
 *             type="object",
 *             @OA\Property(
 *                 property="invoice_no",
 *                 type="string",
 *                 description="Invoice number.",
 *                 example="INV23232YESS_12"
 *             ),
 *             @OA\Property(
 *                 property="confirmation",
 *                 type="string",
 *                 nullable=true,
 *                 description="Confirmation if transaction is successful.",
 *                 example="240712021905968TEST"
 *             ),
 *             @OA\Property(
 *                 property="tokenize_payment",
 *                 type="string",
 *                 nullable=true,
 *                 example=null
 *             ),
 *             @OA\Property(
 *                 property="status_text",
 *                 type="string",
 *                 description="Status of a transaction.",
 *                 example="Cleared"
 *             ),
 *             @OA\Property(
 *                 property="merchant_payee_name",
 *                 type="string",
 *                 description="Merchant or Payee Name.",
 *                 nullable=true,
 *                 example=null
 *             ),
 *             @OA\Property(
 *                 property="schedule_name",
 *                 type="string",
 *                 example="sfvdzfvsd"
 *             ),
 *             @OA\Property(
 *                 property="schedule_purpose",
 *                 type="string",
 *                 example=""
 *             ),
 *             @OA\Property(
 *                 property="deposit_date",
 *                 type="string",
 *                 format="date",
 *                 nullable=true,
 *                 example=null
 *             ),
 *             @OA\Property(
 *                 property="effective_date",
 *                 type="string",
 *                 format="date",
 *                 nullable=true,
 *                 example="07-15-2024"
 *             )
 *         )
 *     }
 * )
 * 
 *  @OA\Schema(
 *     schema="TransactionByDateRange",
 *     type="object",
 *     description="Response schema for transaction or receivable by date range. Response will only contain transactions having originated_date in between provided date range",
 *     @OA\Property(
 *         property="status",
 *         type="integer",
 *         example=200
 *     ),
 *     @OA\Property(
 *         property="message",
 *         type="string",
 *         example="Success"
 *     ),
 *     @OA\Property(
 *         property="data",
 *         type="object",
 *         @OA\Property(
 *             property="transaction_id",
 *             type="string",
 *             example="81383c5c-ee32-42fd-8578-4dc4bbfd11e1"
 *         ),
 *         @OA\Property(
 *             property="source",
 *             type="string",
 *             example="transactions"
 *         ),
 *         @OA\Property(
 *             property="user",
 *             type="object",
 *             @OA\Property(property="id", type="integer", example=25),
 *             @OA\Property(property="name", type="string", example="loreum"),
 *             @OA\Property(property="email", type="string", example="kaswebtester@gmail.com")
 *         ),
 *         @OA\Property(
 *             property="transactions",
 *             type="array",
 *             @OA\Items(ref="#/components/schemas/TransactionByDateRangeMergedSchema")
 *         ),
 *         @OA\Property(
 *             property="pagination",
 *             ref="#/components/schemas/pagination"
 *         )
 *     )
 * )
 * 
 * @OA\Schema(
 *     schema="TransactionByDateRangeMergedSchema",
 *     allOf={
 *         @OA\Schema(ref="#/components/schemas/TransactionCommonSchema"),
 *         @OA\Schema(ref="#/components/schemas/TransactionByDateRangeBaseSchema")
 *     }
 * )
 * 
 * @OA\Schema(
 *     schema="TransactionByDateRangeBaseSchema",
 *     type="object",
 *     description="Transaction schema related to date range",
 *     @OA\Property(
 *         property="source",
 *         type="string",
 *         example="transactions"
 *     ),
 *     @OA\Property(
 *         property="organization_date",
 *         type="string",
 *         format="date",
 *         nullable=true,
 *         example="07-15-2024"
 *     ),
 *     @OA\Property(
 *         property="return_date",
 *         type="string",
 *         format="date",
 *         nullable=true,
 *         example=null
 *     )
 * )
 * 
 *  @OA\Schema(
 *     schema="CancelTransactionByConfirmationId",
 *     type="object",
 *     description="Response schema for canceled transaction.",
 *     @OA\Property(
 *         property="status",
 *         type="integer",
 *         example=200
 *     ),
 *     @OA\Property(
 *         property="message",
 *         type="string",
 *         example="Success"
 *     ),
 *     @OA\Property(
 *         property="data",
 *         type="object",
 *         @OA\Property(
 *             property="transaction_id",
 *             type="string",
 *             example="81383c5c-ee32-42fd-8578-4dc4bbfd11e1"
 *         ),
 *         @OA\Property(
 *             property="source",
 *             type="string",
 *             example="transactions"
 *         ),
 *         @OA\Property(
 *             property="message",
 *             type="string",
 *             example="Transaction cancelled successfully"
 *         ),
 *         @OA\Property(
 *             property="status",
 *             type="string",
 *             example="cancelled"
 *         ),
 *         @OA\Property(
 *             property="transaction",
 *             ref="#/components/schemas/CancelTransactionByConfirmationIdMergedSchema"
 *         )
 *     )
 * )
 * 
 * @OA\Schema(
 *     schema="CancelTransactionByConfirmationIdMergedSchema",
 *     allOf={
 *         @OA\Schema(ref="#/components/schemas/TransactionCommonSchema"),
 *         @OA\Schema(ref="#/components/schemas/CancelTransactionByConfirmationIdSchema")
 *     }
 * )
 * 
 * @OA\Schema(
 *     schema="CancelTransactionByConfirmationIdSchema",
 *     type="object",
 *     description="Fields related to cancel transaction schema",
 *     @OA\Property(
 *         property="user_id",
 *         type="integer",
 *         example=25
 *     ),
 *     @OA\Property(
 *         property="payee",
 *         type="string",
 *         nullable=true,
 *         example=null
 *     ),
 *     @OA\Property(
 *         property="created_date",
 *         type="string",
 *         format="date",
 *         example="07-12-2024"
 *     ),
 *     @OA\Property(
 *         property="originated_date",
 *         type="string",
 *         format="date",
 *         nullable=true,
 *         example=null
 *     ),
 *     @OA\Property(
 *         property="effective_date",
 *         type="string",
 *         format="date",
 *         nullable=true,
 *         example=null
 *     ),
 *     @OA\Property(
 *         property="return_date",
 *         type="string",
 *         format="date",
 *         nullable=true,
 *         example=null
 *     )
 * )
 * 
 * @OA\Schema(
 *     schema="Error",
 *     type="object",
 *     description="Generic error response schema",
 *     @OA\Property(property="status", type="integer", example=400),
 *     @OA\Property(property="message", type="string", example="Invalid request data"),
 *     @OA\Property(property="error", type="string", example="Bad Request")
 * )
 * 
 * @OA\Schema(
 *     schema="Unauthorized",
 *     type="object",
 *     description="Response schema for Unauthorized",
 *     @OA\Property(
 *         property="message",
 *         type="string",
 *         example="Token invalid or missing. Please login again."
 *     )
 * )
 * 
 * @OA\Schema(
 *     schema="NotFound",
 *     type="object",
 *     description="Response schema for Record Not Found",
 *     @OA\Property(
 *         property="status",
 *         type="integer",
 *         example=404
 *     ),
 *     @OA\Property(
 *         property="message",
 *         type="string",
 *         example="Record not found."
 *     ),
 *     @OA\Property(
 *         property="data",
 *         type="object",
 *         @OA\Property(
 *             property="transaction_id",
 *             type="string",
 *             example="81383c5c-ee32-42fd-8578-4dc4bbfd11e1"
 *         )
 *     )
 * )
 * 
 * @OA\Schema(
 *     schema="BadRequest",
 *     type="object",
 *     description="Response schema for Record Not Found",
 *     @OA\Property(
 *         property="status",
 *         type="integer",
 *         example=400
 *     ),
 *     @OA\Property(
 *         property="message",
 *         type="string",
 *         example="Invalid date format. Use YYYY-MM-DD."
 *     ),
 *     @OA\Property(
 *         property="data",
 *         type="object",
 *         @OA\Property(
 *             property="transaction_id",
 *             type="string",
 *             example="81383c5c-ee32-42fd-8578-4dc4bbfd11e1"
 *         )
 *     )
 * )
 * 
 */

class TransactionSchemas {}
