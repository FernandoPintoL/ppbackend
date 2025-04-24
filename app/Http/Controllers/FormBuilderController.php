<?php

namespace App\Http\Controllers;

use App\Models\FormBuilder;
use App\Models\FormCollaborator;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class FormBuilderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = Auth::user();

        // Get forms created by the user
        $ownedForms = FormBuilder::where('user_id', $user->id)->get();

        // Get forms the user is collaborating on (with accepted status)
        $collaboratingForms = $user->collaboratingForms()
            ->wherePivot('status', 'accepted')
            ->get();

        // Get pending invitations
        $pendingInvitations = $user->collaboratingForms()
            ->wherePivot('status', 'pending')
            ->get();

        return Inertia::render('FormBuilder/Index', [
            'ownedForms' => $ownedForms,
            'collaboratingForms' => $collaboratingForms,
            'pendingInvitations' => $pendingInvitations,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Validate the request
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'elements' => 'required|json',
        ]);

        // Create the form
        $form = FormBuilder::create([
            'name' => $validated['name'],
            'elements' => $validated['elements'],
            'user_id' => auth()->id(),
        ]);

        return response()->json($form, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $form = FormBuilder::findOrFail($id);

        return Inertia::render('FormBuilder/Show', [
            'form' => $form
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        // Validate the request
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'elements' => 'sometimes|json',
        ]);

        // Update the form
        $form = FormBuilder::findOrFail($id);
        $form->update($validated);

        return response()->json($form, 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $form = FormBuilder::findOrFail($id);
        $form->delete();

        return response()->json(null, 204);
    }

    /**
     * Invite a user to collaborate on a form.
     */
    public function inviteCollaborator(Request $request, string $id)
    {
        // Validate the request
        $validated = $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        // Get the form and ensure the current user is the owner
        $form = FormBuilder::findOrFail($id);

        if ($form->user_id !== Auth::id()) {
            return response()->json(['message' => 'You are not authorized to invite collaborators to this form'], 403);
        }

        // Find the user to invite
        $userToInvite = User::where('email', $validated['email'])->first();

        // Check if the user is already a collaborator
        $existingCollaboration = FormCollaborator::where('form_builder_id', $form->id)
            ->where('user_id', $userToInvite->id)
            ->first();

        if ($existingCollaboration) {
            return response()->json(['message' => 'This user is already a collaborator or has a pending invitation'], 422);
        }

        // Create the collaboration
        $collaboration = FormCollaborator::create([
            'form_builder_id' => $form->id,
            'user_id' => $userToInvite->id,
            'status' => 'pending',
        ]);

        return response()->json($collaboration, 201);
    }

    /**
     * Accept a collaboration invitation.
     */
    public function acceptInvitation(string $id)
    {
        // Find the collaboration and ensure it belongs to the current user
        $collaboration = FormCollaborator::where('form_builder_id', $id)
            ->where('user_id', Auth::id())
            ->where('status', 'pending')
            ->firstOrFail();

        // Update the status
        $collaboration->update(['status' => 'accepted']);

        return response()->json($collaboration, 200);
    }

    /**
     * Reject a collaboration invitation.
     */
    public function rejectInvitation(string $id)
    {
        // Find the collaboration and ensure it belongs to the current user
        $collaboration = FormCollaborator::where('form_builder_id', $id)
            ->where('user_id', Auth::id())
            ->where('status', 'pending')
            ->firstOrFail();

        // Update the status
        $collaboration->update(['status' => 'rejected']);

        return response()->json($collaboration, 200);
    }

    /**
     * Get collaborators for a form.
     */
    public function getCollaborators(string $id)
    {
        // Get the form
        $form = FormBuilder::with(['collaborators' => function($query) {
            $query->wherePivot('status', 'accepted');
        }])->findOrFail($id);

        // Check if the user is the owner or a collaborator
        $isOwner = $form->user_id === Auth::id();
        $isCollaborator = $form->collaborators->contains('id', Auth::id());

        if (!$isOwner && !$isCollaborator) {
            return response()->json(['message' => 'You are not authorized to view this form\'s collaborators'], 403);
        }

        return response()->json($form->collaborators, 200);
    }

    /**
     * Handle invitation link.
     */
    public function handleInviteLink(string $id)
    {
        // Check if user is authenticated
        if (!Auth::check()) {
            // Redirect to login page with a redirect back to this page after login
            return redirect()->route('login')->with('redirect', route('form-builder.invite-link', $id));
        }

        $user = Auth::user();
        $form = FormBuilder::findOrFail($id);

        // Check if user is already the owner
        if ($form->user_id === $user->id) {
            return redirect()->route('form-builder.index')
                ->with('message', 'You are already the owner of this form.');
        }

        // Check if user is already a collaborator
        $existingCollaboration = FormCollaborator::where('form_builder_id', $form->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existingCollaboration) {
            if ($existingCollaboration->status === 'accepted') {
                return redirect()->route('form-builder.index')
                    ->with('message', 'You are already a collaborator on this form.');
            } elseif ($existingCollaboration->status === 'rejected') {
                // Update status to pending
                $existingCollaboration->update(['status' => 'pending']);
            }
            // If status is pending, show the invitation page
        } else {
            // Create a new collaboration with pending status
            FormCollaborator::create([
                'form_builder_id' => $form->id,
                'user_id' => $user->id,
                'status' => 'pending',
            ]);
        }

        // Redirect to form builder index with the invitation highlighted
        return redirect()->route('form-builder.index')
            ->with('highlight_invitation', $id)
            ->with('message', 'You have been invited to collaborate on "' . $form->name . '". Please check your pending invitations.');
    }
}
