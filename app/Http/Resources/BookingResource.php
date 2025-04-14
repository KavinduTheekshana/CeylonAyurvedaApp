<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'service_id' => $this->service_id,
            'service' => [
                'id' => $this->whenLoaded('service', function () {
                    return $this->service->id;
                }),
                'name' => $this->whenLoaded('service', function () {
                    return $this->service->title;
                }),
                'price' => $this->whenLoaded('service', function () {
                    return $this->service->price;
                }),
                'image' => $this->whenLoaded('service', function () {
                    return $this->service->image ? url('storage/' . $this->service->image) : null;
                }),
            ],
            'date' => $this->date,
            'time' => $this->time,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'address_line1' => $this->address_line1,
            'address_line2' => $this->address_line2,
            'city' => $this->city,
            'postcode' => $this->postcode,
            'notes' => $this->notes,
            'price' => (float)$this->price,
            'reference' => $this->reference,
            'status' => $this->status,
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
            'formatted_date' => $this->date ? date('D, M d, Y', strtotime($this->date)) : null,
            'formatted_time' => $this->time ? date('h:i A', strtotime($this->time)) : null,
            'can_cancel' => $this->date ? strtotime($this->date) > strtotime(date('Y-m-d')) && $this->status !== 'cancelled' : false,
        ];
    }
}
