<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Config;
use Illuminate\Http\Request;

class ConfigController extends Controller
{
    public function index() {
        $configs = Config::all();
        return response()->json([
            'success' => true,
            'message' => 'Successfully retrieved all configs',
            'data' => $configs,
        ], 200);
    }

    public function show(int $id) {
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
