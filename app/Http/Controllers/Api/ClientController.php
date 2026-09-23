<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateClientRequest;
use App\Http\Resources\Api\ClientResource;
use App\Models\Client;
use App\Models\User;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function index($id)
    {
        $client = $this->ownedClient($id);
        if (!$client)
            return response()->json(["message" => "This action is unauthorized"], 403);

        return response()->json([
            "data" => new ClientResource($client)
        ]);
    }
    public function update(UpdateClientRequest $request, $national_id)
    {
        if (is_numeric($national_id)) {
            $client = $this->ownedClient($national_id);
            if (!$client)
                return response()->json(["message" => "This action is unauthorized"], 403);

            try {
                // find user related to client and update
                $userData = [];
                $userData['name'] = $request->name;
                User::where('id', $client->user_id)->update($userData);
                $clientData = [];
                //  handle image
                if ($request->hasFile('avatar_image')) {
                    $avatar = $request->file('avatar_image');
                    $clientData['avatar_image'] = $avatar->getClientOriginalName();
                    $avatar->storeAs('public/clients_Images', $clientData['avatar_image']);
                } else {
                    $clientData['avatar_image'] = 'default.jpg';
                }
                // $clientData['id'] = $request->id; //national id 
                $clientData['date_of_birth'] = $request->date_of_birth;
                $clientData['gender'] = $request->gender;
                $clientData['phone'] = $request->phone;
                $client->update($clientData);
            } catch (\Illuminate\Database\QueryException $exception) {

                return "an error occurs, please try again later!!";
            }
            return response()->json([
                "message" => "Client updated successfully",
                "data" => new ClientResource($client->refresh())
            ]);
        }
    }

    private function ownedClient($national_id)
    {
        $auth_user = auth()->user();
        return Client::where("user_id", '=', $auth_user->id)->find($national_id);
    }
}
