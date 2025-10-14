<?php

namespace App\Schema\V1;

use OpenApi\Annotations as OA;

/**
 * 
 * @OA\Schema(
 *     schema="GetPayeeByIdSchema",
 *     type="object",
 *     description="Get Payee By Id Schema.",
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
 *             property="user_id",
 *             type="integer",
 *             example=25
 *         ),
 *         @OA\Property(
 *             property="source",
 *             type="string",
 *             example="payees"
 *         ),
 *         @OA\Property(
 *             property="data",
 *             ref="#/components/schemas/PayeeCommonSchema"
 *         ),
 *         @OA\Property(
 *             property="Primary Account",
 *             ref="#/components/schemas/PrimaryAccount"
 *         ),
 *         @OA\Property(
 *             property="Additional Banks",
 *             type="array",
 *             @OA\Items(ref="#/components/schemas/PrimaryAccount")
 *         )
 *     )
 * )
 * 
 * @OA\Schema(
 *     schema="PrimaryAccount",
 *     type="object",
 *     description="Response schema for primary account.",
 *     @OA\Property(
 *         property="id",
 *         type="integer",
 *         example=108
 *     ),
 *     @OA\Property(
 *         property="payee_id",
 *         type="integer",
 *         example=121
 *     ),
 *     @OA\Property(
 *         property="account_holder_name",
 *         type="string",
 *         example="Demo Account"
 *     ),
 *     @OA\Property(
 *         property="routing_no",
 *         type="string",
 *         example="555555550"
 *     ),
 *     @OA\Property(
 *         property="account_no",
 *         type="string",
 *         example="555555550"
 *     ),
 *     @OA\Property(
 *         property="account_type",
 *         type="string",
 *         example="Demo Account"
 *     ),
 *     @OA\Property(
 *         property="created_at",
 *         type="string",
 *         format="date",
 *         example="10-01-2025"
 *     ),
 *     @OA\Property(
 *         property="updated_at",
 *         type="string",
 *         format="date",
 *         example="10-01-2025"
 *     )
 * )
 * 
 * @OA\Schema(
 *     schema="GetPayeeSchema",
 *     type="object",
 *     description="Response schema for getting payee details by ID.",
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
 *             property="user_id",
 *             type="integer",
 *             example=25
 *         ),
 *         @OA\Property(
 *             property="source",
 *             type="string",
 *             example="payees"
 *         ),
 *         @OA\Property(
 *             property="payees",
 *             type="array",
 *             description="List of payees with account and bank details",
 *             @OA\Items(
 *                 type="object",
 *                 @OA\Property(
 *                     property="data",
 *                     allOf={
 *                         @OA\Schema(ref="#/components/schemas/PayeeCommonSchema"),
 *                         @OA\Schema(
 *                             type="object",
 *                             @OA\Property(property="created_at", type="string", format="date", example="07-02-2024"),
 *                             @OA\Property(property="updated_at", type="string", format="date", example="10-09-2025")
 *                         )
 *                     }
 *                 ),
 *                 @OA\Property(
 *                     property="Primary Account",
 *                     ref="#/components/schemas/PrimaryAccount"
 *                 ),
 *                 @OA\Property(
 *                     property="Additional Banks",
 *                     type="array",
 *                     @OA\Items(ref="#/components/schemas/PrimaryAccount")
 *                 )
 *             )
 *         ),
 *         @OA\Property(
 *             property="pagination",
 *             ref="#/components/schemas/pagination"
 *         )
 *     )
 * )
 * 
 * @OA\Schema(
 *     schema="PayeeCommonSchema",
 *     type="object",
 *     description="Common keys for payee",
 *     @OA\Property(
 *         property="id",
 *         type="integer",
 *         example=25
 *     ),
 *     @OA\Property(
 *         property="user_id",
 *         type="integer",
 *         example=25
 *     ),
 *     @OA\Property(
 *         property="payee_type",
 *         type="string",
 *         example="vendor"
 *     ),
 *     @OA\Property(
 *         property="payee_name",
 *         type="string",
 *         example="loreum"
 *     ),
 *     @OA\Property(
 *         property="email",
 *         type="string",
 *         example="example@gmail.com"
 *     ),
 *     @OA\Property(
 *         property="phone_no",
 *         type="string",
 *         example="04353534545"
 *     ),
 *     @OA\Property(
 *         property="address1",
 *         type="string",
 *         example="street 1 ,232 ,CA"
 *     ),
 *     @OA\Property(
 *         property="address2",
 *         type="string",
 *         example="street 1 ,232 ,CA"
 *     ),
 *     @OA\Property(
 *         property="city",
 *         type="string",
 *         example="fgbfb"
 *     ),
 *     @OA\Property(
 *         property="state",
 *         type="string",
 *         example="NY"
 *     ),
 *     @OA\Property(
 *         property="zip",
 *         type="string",
 *         example="12345"
 *     ),
 *     @OA\Property(
 *         property="country",
 *         type="string",
 *         example="US"
 *     )
 * )
 * 
 * @OA\Schema(
 *     schema="CreatePayeeSchema",
 *     type="object",
 *     description="Create Payee Response Schema",
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
 *             property="id",
 *             type="integer",
 *             example=1
 *         ),
 *         @OA\Property(
 *             property="message",
 *             type="string",
 *             example="Payee data has been successfully inserted"
 *         )
 *     )
 * )
 * 
 * @OA\Schema(
 *     schema="CreatePayeeValidationErrorSchema",
 *     type="object",
 *     description="Validation error response schema when input fields are missing or invalid.",
 *     @OA\Property(
 *         property="status",
 *         type="integer",
 *         description="HTTP status code for validation failure.",
 *         example=422
 *     ),
 *     @OA\Property(
 *         property="message",
 *         type="string",
 *         description="General error message.",
 *         example="The payee external id field is required."
 *     ),
 *     @OA\Property(
 *         property="data",
 *         type="object",
 *         description="Detailed validation errors per field.",
 *         @OA\Property(
 *             property="payee_external_id",
 *             type="array",
 *             @OA\Items(
 *                 type="string",
 *                 example="The payee external id field is required."
 *             )
 *         )
 *     )
 * )
 * 
 * @OA\Schema(
 *     schema="UpdatePayeeSchema",
 *     @OA\Property(
 *         property="data",
 *         type="object",
 *         @OA\Property(property="message", type="string", example="Success"),
 *         @OA\Property(
 *             property="payee",
 *             allOf={
 *                 @OA\Schema(ref="#/components/schemas/PayeeCommonSchema"),
 *                 @OA\Schema(
 *                     type="object",
 *                     @OA\Property(property="unique_id", type="string", example="WdhjJ"),
 *                     @OA\Property(property="nickname", type="string", example="Updated Name"),
 *                     @OA\Property(property="account_no", type="string", example="12121212121"),
 *                     @OA\Property(property="created_at", type="string", format="date-time", example="2024-07-02T16:30:43.000000Z"),
 *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-10-09T19:24:59.000000Z")
 *                 )
 *             }
 *         )
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="PayeeNotFoundSchema",
 *     type="object",
 *     description="Error response schema when a Payee is not found",
 *     @OA\Property(
 *         property="error",
 *         type="string",
 *         example="Payee not found"
 *     )
 * )
 */

class PayeeSchemas {}
