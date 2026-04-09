<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContactTag;
use App\Models\WhatsAppContact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContactTagController extends Controller
{
    /**
     * List all tags for the current user.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $userId = auth()->user()->getEffectiveUserId();

            if (! $userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required',
                ], 401);
            }

            // RLS global scope on ContactTag handles user_id filtering
            $tags = ContactTag::withCount('contacts')
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $tags,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve tags',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a new tag.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:50',
            'color' => [
                'required',
                'string',
                'max:7',
                'regex:/^#[0-9a-fA-F]{6}$/',
                Rule::in(ContactTag::PRESET_COLORS),
            ],
        ]);

        try {
            $userId = auth()->user()->getEffectiveUserId();

            if (! $userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required',
                ], 401);
            }

            // Check for duplicate name (case-insensitive)
            $exists = ContactTag::where('name', $request->name)->exists();
            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'A tag with this name already exists',
                ], 422);
            }

            $tag = ContactTag::create([
                'user_id' => $userId,
                'name' => $request->name,
                'color' => $request->color,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Tag created successfully',
                'data' => $tag->loadCount('contacts'),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create tag',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update an existing tag.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:50',
            'color' => [
                'sometimes',
                'required',
                'string',
                'max:7',
                'regex:/^#[0-9a-fA-F]{6}$/',
                Rule::in(ContactTag::PRESET_COLORS),
            ],
        ]);

        try {
            $userId = auth()->user()->getEffectiveUserId();

            if (! $userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required',
                ], 401);
            }

            // RLS global scope ensures tag belongs to user
            $tag = ContactTag::findOrFail($id);

            // Check for duplicate name if name is being changed
            if ($request->has('name') && $request->name !== $tag->name) {
                $exists = ContactTag::where('name', $request->name)
                    ->where('id', '!=', $tag->id)
                    ->exists();
                if ($exists) {
                    return response()->json([
                        'success' => false,
                        'message' => 'A tag with this name already exists',
                    ], 422);
                }
            }

            $tag->update($request->only(['name', 'color']));

            return response()->json([
                'success' => true,
                'message' => 'Tag updated successfully',
                'data' => $tag->fresh()->loadCount('contacts'),
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Tag not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update tag',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a tag. Cascade deletes pivot rows.
     */
    public function destroy($id): JsonResponse
    {
        try {
            $userId = auth()->user()->getEffectiveUserId();

            if (! $userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required',
                ], 401);
            }

            // RLS global scope ensures tag belongs to user
            $tag = ContactTag::findOrFail($id);
            $tag->delete();

            return response()->json([
                'success' => true,
                'message' => 'Tag deleted successfully',
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Tag not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete tag',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Assign tags to a contact.
     * Accepts an array of tag IDs to sync/attach.
     */
    public function assignTags(Request $request, $contactId): JsonResponse
    {
        $request->validate([
            'tag_ids' => 'required|array',
            'tag_ids.*' => 'integer|exists:contact_tags,id',
        ]);

        try {
            $userId = auth()->user()->getEffectiveUserId();

            if (! $userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required',
                ], 401);
            }

            // RLS global scope ensures contact belongs to user
            $contact = WhatsAppContact::findOrFail($contactId);

            // Verify all tag IDs belong to the current user (RLS handles this)
            $validTagIds = ContactTag::whereIn('id', $request->tag_ids)->pluck('id');

            // Sync replaces all current tags with the provided ones
            $contact->tags()->sync($validTagIds);

            return response()->json([
                'success' => true,
                'message' => 'Tags assigned successfully',
                'data' => $contact->load('tags'),
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Contact not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign tags',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove a tag from a contact.
     */
    public function removeTag($contactId, $tagId): JsonResponse
    {
        try {
            $userId = auth()->user()->getEffectiveUserId();

            if (! $userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required',
                ], 401);
            }

            // RLS global scope ensures contact belongs to user
            $contact = WhatsAppContact::findOrFail($contactId);

            // RLS global scope ensures tag belongs to user
            $tag = ContactTag::findOrFail($tagId);

            $contact->tags()->detach($tagId);

            return response()->json([
                'success' => true,
                'message' => 'Tag removed successfully',
                'data' => $contact->load('tags'),
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Contact or tag not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove tag',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
