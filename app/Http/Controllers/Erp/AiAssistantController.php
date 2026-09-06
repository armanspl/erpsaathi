<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Services\AiAssistantService;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class AiAssistantController extends Controller
{
    public function chat(Request $request, AiAssistantService $assistant)
    {
        $data = $request->validate([
            'message' => 'required|string|max:2000',
            'history' => 'nullable|array|max:12',
            'history.*.role' => 'required_with:history|in:user,assistant',
            'history.*.content' => 'required_with:history|string|max:4000',
        ]);

        try {
            $reply = $assistant->chat(
                trim($data['message']),
                $data['history'] ?? []
            );

            return response()->json(['reply' => $reply]);
        } catch (RuntimeException $e) {
            $status = in_array($e->getCode(), [502, 503], true) ? (int) $e->getCode() : 502;

            return response()->json(['message' => $e->getMessage()], $status);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Assistant request failed.'], 500);
        }
    }
}
