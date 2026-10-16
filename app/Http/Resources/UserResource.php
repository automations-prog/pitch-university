<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'status' => $this->status,
            'work_location' => $this->work_location,
            'team_id' => $this->team_id,
            'team' => $this->whenLoaded('team', fn () => $this->team === null ? null : [
                'id' => $this->team->id,
                'name' => $this->team->name,
                'campaign' => $this->team->relationLoaded('campaign')
                    ? ['id' => $this->team->campaign->id, 'name' => $this->team->campaign->name]
                    : null,
                'manager' => $this->team->relationLoaded('manager') ? $this->team->manager?->name : null,
            ]),
            'email_verified_at' => $this->email_verified_at,
            'created_at' => $this->created_at,
            'licenses' => LicenseResource::collection($this->whenLoaded('licenses')),
            'abilities' => $this->when($request->routeIs('admin.users.*') && $request->user() !== null, fn () => [
                'update' => $request->user()->can('update', $this->resource),
                'delete' => $request->user()->can('delete', $this->resource),
                'impersonate' => $request->user()->can('impersonate', $this->resource),
            ]),
        ];
    }
}
