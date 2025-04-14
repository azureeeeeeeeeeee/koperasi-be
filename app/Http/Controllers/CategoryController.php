<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function create_category(Request $request) {
        $fields = $request->validate([
            'name' => 'required|string|max:30',
            'potongan' => 'required|numeric|min:0',
        ]);

        $category = Category::create($fields);

        return response()->json([
            'message' => 'kategori berhasil ditambahkan'
        ]);
    }


    public function get_all_categories(Request $request) {
        $categories = Category::all();

        return response()->json([
            'message' => 'kategori berhasil diambil dari database',
            'data' => $categories
        ]);
    }
}
