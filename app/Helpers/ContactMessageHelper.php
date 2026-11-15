<?php

namespace App\Helpers;

use App\Models\Contact;
use App\Models\ContactsState;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ContactMessageHelper
{
    public static function list(): JsonResponse
    {
        $messages = Contact::with(['currentState', 'states'])->latest('id')->get();
        $teamNames = Team::whereIn('team_id', $messages->flatMap->states->pluck('team_id')->unique())
            ->pluck('full_name', 'team_id');

        return response()->json([
            'success' => true,
            'message' => 'Contact messages retrieved.',
            'data' => $messages->map(fn (Contact $c) => self::present($c, $teamNames->all()))->values(),
        ], 200);
    }

    public static function setState(Request $request): JsonResponse
    {
        $contact = Contact::where('con_id', $request->con_id)->firstOrFail();
        self::moveTo($contact, $request->state, $request->attributes->get('team')->team_id, $request->input('note'));

        return self::one($contact);
    }

    public static function reply(Request $request): JsonResponse
    {
        $contact = Contact::where('con_id', $request->con_id)->firstOrFail();
        $reply = trim($request->message);

        $sent = AccountMailHelper::send(
            $contact->email,
            $contact->name,
            'Re: Your message to Ariana Auto Service',
            'Thanks for getting in touch',
            $reply,
            ['rows' => [['Your message', Str::limit($contact->message, 300)]]]
        );

        if (!$sent) {
            return response()->json([
                'success' => false,
                'message' => "The reply couldn't be emailed. Please try again.",
            ], 502);
        }

        self::moveTo($contact, ContactsState::REPLIED, $request->attributes->get('team')->team_id, $reply);

        return self::one($contact);
    }

    /** Lets the sender know their message arrived. */
    public static function acknowledge(Contact $contact): void
    {
        AccountMailHelper::send(
            $contact->email,
            $contact->name,
            'We received your message',
            'Thanks for contacting us',
            'We have received your message and a member of our team will get back to you shortly, usually within one business day.',
            ['rows' => [['Interested in', $contact->interested], ['Your message', Str::limit($contact->message, 300)]]]
        );
    }

    private static function moveTo(Contact $contact, string $code, string $teamId, ?string $note): void
    {
        DB::transaction(function () use ($contact, $code, $teamId, $note) {
            $state = ContactsState::create([
                'con_id' => $contact->con_id,
                'state_id' => (string) Str::uuid(),
                'name' => ContactsState::LABELS[$code],
                'code' => $code,
                'team_id' => $teamId,
                'note' => $note ? trim($note) : null,
            ]);

            $contact->update(['state_id' => $state->state_id]);
        });
    }

    private static function one(Contact $contact): JsonResponse
    {
        $contact = $contact->fresh(['currentState', 'states']);
        $teamNames = Team::whereIn('team_id', $contact->states->pluck('team_id'))->pluck('full_name', 'team_id');

        return response()->json([
            'success' => true,
            'message' => 'Contact message updated.',
            'data' => self::present($contact, $teamNames->all()),
        ], 200);
    }

    private static function present(Contact $contact, array $teamNames): array
    {
        $code = strtoupper((string) ($contact->currentState?->code ?? ContactsState::NEW));

        return [
            'con_id' => $contact->con_id,
            'name' => $contact->name,
            'email' => $contact->email,
            'interested' => $contact->interested,
            'message' => $contact->message,
            'status' => array_key_exists($code, ContactsState::LABELS) ? $code : ContactsState::NEW,
            'created_at' => $contact->created_at?->toIso8601String(),
            'history' => $contact->states->map(fn (ContactsState $s) => [
                'status' => strtoupper((string) $s->code),
                'note' => $s->note,
                'by' => $teamNames[$s->team_id] ?? null,
                'at' => $s->created_at?->toIso8601String(),
            ])->values(),
        ];
    }
}
