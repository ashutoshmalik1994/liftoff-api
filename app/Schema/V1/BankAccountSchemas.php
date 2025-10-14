<?php

namespace App\Schema\V1;

use OpenApi\Annotations as OA;

/**
 * 
 * @OA\Schema(
 *     schema="GetAllBanksResponse",
 *     type="object",
 *     description="Top-level API response for fetching all user bank accounts.",
 *     @OA\Property(property="status", type="integer", example=200),
 *     @OA\Property(property="message", type="string", example="200"),
 *     @OA\Property(
 *         property="data",
 *         type="object",
 *         @OA\Property(property="status", type="string", example="success"),
 *         @OA\Property(
 *             property="data",
 *             type="array",
 *             description="List of user's linked bank accounts",
 *             @OA\Items(ref="#/components/schemas/BankAccountItem")
 *         ),
 *         @OA\Property(property="pagination", ref="#/components/schemas/pagination")
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="BankAccountItem",
 *     type="object",
 *     description="Detailed schema for a single bank account record.",
 *     @OA\Property(property="id", type="integer", example=65),
 *     @OA\Property(property="user_id", type="string", example="25"),
 *     @OA\Property(property="country", type="string", example="US"),
 *     @OA\Property(property="name", type="string", example="customer bank"),
 *     @OA\Property(property="account_no", type="string", example="*****6789"),
 *     @OA\Property(property="routing_no", type="string", example="*****5550"),
 *     @OA\Property(property="transit_no", type="string", nullable=true, example=null),
 *     @OA\Property(property="financial_institute_no", type="string", nullable=true, example=null),
 *     @OA\Property(property="bank_account_type", type="string", example="savings"),
 *     @OA\Property(property="bank_name", type="string", example="Bank of America"),
 *     @OA\Property(property="bank_street_address", type="string", example="123 Main St"),
 *     @OA\Property(property="bank_city", type="string", example="Los Angeles"),
 *     @OA\Property(property="bank_state", type="string", example="CA"),
 *     @OA\Property(property="bank_zip", type="string", example="90001"),
 *     @OA\Property(property="account_status", type="string", example="pending"),
 *     @OA\Property(property="current_balance", type="string", example="0"),
 *     @OA\Property(property="available_balance", type="string", example="0"),
 *     @OA\Property(property="bank_confirmation_id", type="string", nullable=true, example="250312080213MIW")
 * )
 *
 */
class BankAccountSchemas {}
