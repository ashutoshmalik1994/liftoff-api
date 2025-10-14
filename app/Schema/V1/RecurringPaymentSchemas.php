<?php

namespace App\Schema\V1;

use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="RecurringPaymentListResponse",
 *     type="object",
 *     description="Response schema for listing all recurring payment schedules with pagination",
 *     @OA\Property(property="status", type="string", example="success"),
 *     @OA\Property(
 *         property="data",
 *         type="array",
 *         description="List of recurring payment schedules",
 *         @OA\Items(ref="#/components/schemas/RecurringPaymentItem")
 *     ),
 *     @OA\Property(property="pagination", ref="#/components/schemas/pagination")
 * )
 * 
 * @OA\Schema(
 *     schema="RecurringPaymentItem",
 *     type="object",
 *     description="Single recurring payment record details",
 *     @OA\Property(property="id", type="integer", example=12),
 *     @OA\Property(property="status", type="string", example="active"),
 *     @OA\Property(property="user_id", type="integer", example=25),
 *     @OA\Property(property="recurring", type="string", example="monthly"),
 *     @OA\Property(property="first_payment_date", type="string", format="date", example="07-12-2024"),
 *     @OA\Property(property="last_bill_date", type="string", format="date", example="08-12-2024"),
 *     @OA\Property(property="number_of_payments", type="integer", example=6),
 *     @OA\Property(property="amount", type="number", format="float", example=1250.75),
 *     @OA\Property(property="payer", type="string", example="Demo Payer"),
 *     @OA\Property(property="payer_id", type="string", example="ibank_22"),
 *     @OA\Property(property="payable_to", type="string", example="Demo Bank Account"),
 *     @OA\Property(property="payable_to_id", type="integer", example=32),
 *     @OA\Property(property="schedule_name", type="string", example="Quarterly Rent"),
 *     @OA\Property(property="schedule_purpose", type="string", example="Office Space"),
 *     @OA\Property(property="count_payments", type="integer", example=3),
 *     @OA\Property(property="next_bill_date", type="string", format="date", example="09-12-2024"),
 *     @OA\Property(property="created_at", type="string", format="date", example="07-01-2024"),
 *     @OA\Property(property="updated_at", type="string", format="date", example="07-15-2024")
 * )
 * 
 * @OA\Schema(
 *     schema="InternalServerError",
 *     type="object",
 *     description="Generic internal server error response",
 *     @OA\Property(property="status", type="string", example="error"),
 *     @OA\Property(property="message", type="string", example="An error occurred while fetching recurring payments"),
 *     @OA\Property(property="error", type="string", example="SQLSTATE[HY000]: General error ...")
 * )
 */
class RecurringPaymentSchemas {}
