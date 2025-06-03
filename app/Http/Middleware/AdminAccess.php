<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && $request->user()->tipe === 'admin' || $request->user()->tipe === 'pegawai') {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => 'Akses ditolak. Hanya admin yang dapat mengakses endpoint ini.'
        ], 403);
    }
}