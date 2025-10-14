<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * @OA\Info(
 *     version="1.0.0",
 *     title="Liftoff Card Integration API",
 *     description="Defines the interface for programmatic interaction with the ACH payment processing system. This specification details resources for managing payees (merchants), originating individual ACH transactions, administering recurring payment schedules, and retrieving real-time status updates throughout the transaction lifecycle, including confirmation of settlement or details of returns.",
 *     @OA\Contact(
 *	        name="Liftoff Card Support / Grid Funding Integration Team",
 *         email="support@liftoffcard.com"
 *     )
 * )
 *
 * @OA\Server(
 *     url=L5_SWAGGER_CONST_HOST,
 *     description="Production server"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     in="header",
 *     name="Authorization",
 *     description="Enter JWT Bearer token"
 * )
 */
class SwaggerController extends Controller
{
    // Bas annotation ke liye hai ye class, koi code nahi chahiye
}
