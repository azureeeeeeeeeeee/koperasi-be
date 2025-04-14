<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CategoryController extends Controller
{
    public function create_category(Request $request) {
        Gate::authorize('create', Category::class);
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

    public function get_one_category(Request $request, $id) {
        Gate::authorize('view', Category::class);
        $category = Category::where('id', $id)->firstOrFail();

        return response()->json([
            'message' => 'kategori berhasil diambil dari database',
            'data' => $category
        ]);
    }
    
    public function update_category(Request $request, $id) {
        Gate::authorize('update', Category::class);
        $category = Category::where('id', $id)->firstOrFail();
        
        $fields = $request->validate([
            'potongan' => 'required|numeric|min:0',
        ]);
        
        $category->potongan = $fields['potongan'];
        $category->save();
        
        return response()->json([
            'message' => 'berhasil edit kategori',
            'data' => $category
        ]);
    }
    
    public function delete_category(Request $request, $id) {
        Gate::authorize('delete', Category::class);
        $category = Category::where('id', $id)->firstOrFail();

        $category->delete();

        return response()->json([
            'message' => 'kategori berhasil dihapus'
        ]);
    }
}
