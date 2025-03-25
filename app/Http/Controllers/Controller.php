<?php

namespace App\Http\Controllers;

/**
 * @OA\Info(title="API Documentation for Koperasi Backend", version="1.0")
 * @OA\SecurityScheme(
 *     type="http",
 *     securityScheme="bearerAuth",
 *     scheme="bearer"
 * )
 * @OA\PathItem(path="/api")
 * 
 * @OA\Tag(
 *     name="Authentication",
 *     description="API endpoints for user authentication"
 * )
 * @OA\Tag(
 *     name="Admin",
 *     description="API endpoints for admin control"
 * )
 */
abstract class Controller
{
    //
}
