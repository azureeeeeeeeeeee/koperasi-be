<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Config;
// use Illuminate\Auth\Access\Gate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ConfigController extends Controller
{
    public function index() {
        Gate::authorize('viewAny', Config::class);
        $configs = Config::all();
        return response()->json([
            'success' => true,
            'message' => 'Successfully retrieved all configs',
            'data' => $configs,
        ], 200);
    }

    public function show(int $id) {
        Gate::authorize('view', Config::class);
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

    public function create(Request $request) {
        Gate::authorize('create', Config::class);
        $fields = $request->validate([
            'key' => 'required|string|max:255|unique:configs',
            'value' => 'required|string|max:255',
        ]);

        $config = Config::create($fields);
        return response()->json([
            'success' => true,
            'message' => 'Successfully created config',
            'data' => $config,
        ], 201);
    }

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
        ]);

        $config->update($fields);
        return response()->json([
            'success' => true,
            'message' => 'Successfully updated config',
            'data' => $config,
        ], 200);
    }
    
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
