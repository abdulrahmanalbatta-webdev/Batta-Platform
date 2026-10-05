<?php

namespace App\Http\Controllers\Site;

use App\Enums\LeadService;
use App\Http\Controllers\Controller;
use App\Models\Lead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectRequestController extends Controller
{
    /**
     * The "start a project" form: a new lead in the dashboard's pipeline (which alerts the owner and admins).
     * The hidden "website" field is a bot trap, as on the contact form.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'service' => ['required', Rule::enum(LeadService::class)],
            'budget' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'details' => ['required', 'string', 'min:10', 'max:5000'],
            'website' => ['nullable', 'string', 'max:255'],
        ]);

        if (blank($validated['website'] ?? null)) {
            Lead::query()->create([
                ...collect($validated)->only(['name', 'company', 'email', 'phone', 'service', 'budget'])->all(),
                'note' => $validated['details'],
            ]);
        }

        return response()->json(['message' => 'وصل طلبك، سنتواصل معك خلال يومي عمل.'], 201);
    }
}
