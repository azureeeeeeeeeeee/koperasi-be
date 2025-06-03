<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Config;
// use Illuminate\Auth\Access\Gate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;



/**
 * @OA\Schema(
 *     schema="Config",
 *     type="object",
 *     title="Config",
 *     required={"name", "potongan"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="key", type="string", example="iuran wajib"),
 *     @OA\Property(property="value", type="string", example="15000"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-01T00:00:00Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2024-01-01T00:00:00Z")
 * )
 */
class ConfigController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/config",
     *     summary="Get all config entries",
     *     tags={"Config"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of all config entries",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Successfully retrieved all configs"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Config"))
     *         )
     *     )
     * )
     */
    public function index() {
        Gate::authorize('viewAny', Config::class);
        $configs = Config::all();
        return response()->json([
            'success' => true,
            'message' => 'Successfully retrieved all configs',
            'data' => $configs,
        ], 200);
    }



    /**
     * @OA\Get(
     *     path="/api/config/{id}",
     *     summary="Get a single config by ID",
     *     tags={"Config"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Config found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Successfully retrieved config"),
     *             @OA\Property(property="data", ref="#/components/schemas/Config")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Config not found")
     * )
     */
    public function show(int $id) {
        // Gate::authorize('view', Config::class);
        $config = Config::find($id);
        if (!$config) {
            return response()->json([
                'success' => false,
                'message' => 'Config not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Successfully retrieved config',
            'data' => $config,
        ], 200);
    }

    /**
     * @OA\Post(
     *     path="/api/config",
     *     summary="Create a new config entry",
     *     tags={"Config"},
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"key", "value"},
     *             @OA\Property(property="key", type="string", example="iuran wajib"),
     *             @OA\Property(property="value", type="string", example="15000")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Successfully created config",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Successfully created config"),
     *             @OA\Property(property="data", ref="#/components/schemas/Config")
     *         )
     *     )
     * )
     */
    public function create(Request $request) {
        Gate::authorize('create', Config::class);
        $fields = $request->validate([
            'key' => 'required|string|max:255|unique:configs',
            'key2' => 'required|integer',
            'value' => 'required|string|max:255',
        ]);

        $config = Config::create($fields);
        return response()->json([
            'success' => true,
            'message' => 'Successfully created config',
            'data' => $config,
        ], 201);
    }




    /**
     * @OA\Put(
     *     path="/api/config/{id}",
     *     summary="Update a config entry by ID",
     *     tags={"Config"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="key", type="string", example="iuran wajib"),
     *             @OA\Property(property="value", type="string", example="20000")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successfully updated config",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Successfully updated config"),
     *             @OA\Property(property="data", ref="#/components/schemas/Config")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Config not found")
     * )
     */
    public function update(Request $request, int $id) {
        Gate::authorize('update', Config::class);
        $config = Config::find($id);
        if (!$config) {
            return response()->json([
                'success' => false,
                'message' => 'Config not found',
            ], 404);
        }

        $fields = $request->validate([
            'key' => 'sometimes|required|string|max:255|unique:configs,key,' . $id,
            'value' => 'sometimes|required|string|max:255',
            'key2' => 'sometimes|required|integer',
        ]);

        $config->update($fields);
        return response()->json([
            'success' => true,
            'message' => 'Successfully updated config',
            'data' => $config,
        ], 200);
    }
    




    /**
     * @OA\Delete(
     *     path="/api/config/{id}",
     *     summary="Delete a config entry by ID",
     *     tags={"Config"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successfully deleted config",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Successfully deleted config")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Config not found")
     * )
     */
    public function delete(int $id) {
        Gate::authorize('delete', Config::class);
        $config = Config::find($id);
        if (!$config) {
            return response()->json([
                'success' => false,
                'message' => 'Config not found',
            ], 404);
        }

        $config->delete();
        return response()->json([
            'success' => true,
            'message' => 'Successfully deleted config',
        ], 200);
    }
}
