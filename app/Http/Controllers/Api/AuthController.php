<?php
namespace App\Http\Controllers\Api;

use Abraham\TwitterOAuth\TwitterOAuth;
use App\SocialNetwork;
use App\User;
use Facebook\Facebook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends BaseController
{
    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');
        try {
            if (!$token = JWTAuth::attempt($credentials)) {
                return $this->responseErrors(config('code.user.invalid_credentials'), trans('messages.user.invalid_credentials'), 401);
            }
        } catch (JWTException $e) {
            return $this->responseErrors(config('code.user.login_email_failed'), trans('messsages.user.could_not_create_token'), 500);
        }

        $user = JWTAuth::toUser($token);

        return $this->responseSuccess(compact('token', 'user'));
    }

    public function facebook(Request $request)
    {
        $facebook = $request->only('access_token');
        if (!$facebook || !isset($facebook['access_token'])) {
            return $this->responseErrors(config('code.user.login_facebook_failed'), trans('messages.user.login_facebook_failed'));
        }

        $fb = new Facebook([
            'app_id' => config('services.facebook.app_id'),
            'app_secret' => config('services.facebook.app_secret'),
        ]);

        try {
            $response = $fb->get('/me?fields=id,name,email,link,birthday', $facebook['access_token']);
            $profile = $response->getGraphUser();
            if (!$profile || !isset($profile['id'])) {
                return $this->responseErrors(config('code.user.login_facebook_failed'), trans('messages.user.login_facebook_failed'));
            }

            $email = $profile['email'] ?? null;
            $social = SocialNetwork::where('social_id', $profile['id'])->where('type', config('user.social_network.type.facebook'))->first();
            if ($social) {
                $user = $social->user;
                $social->social_profile = $profile->asJson();
                $social->save();
            } else {
                $user = $email ? User::firstOrCreate(['email' => $email]) : User::create();
                $user->socialNetwork()->create([
                    'social_id' => $profile['id'],
                    'type' => config('user.social_network.type.facebook'),
                ]);
                $user->name = $profile['name'];
                $user->save();
            }

            $token = JWTAuth::fromUser($user);

            return $this->responseSuccess(compact('token', 'user'));
        } catch (\Exception $e) {
            Log::error('Error when login with facebook: ' . $e->getMessage());
            return $this->responseErrors(config('code.user.login_facebook_failed'), trans('messages.user.login_facebook_failed'));
        }
    }

    public function twitter(Request $request)
    {
        $twitter = $request->only('access_token', 'access_token_secret');
        if (!$twitter || !isset($twitter['access_token']) || !isset($twitter['access_token_secret'])) {
            return $this->responseErrors(config('code.user.login_twitter_failed'), trans('messages.user.login_twitter_failed'));
        }

        $tw = new TwitterOAuth(
            config('services.twitter.consumer_key'),
            config('services.twitter.consumer_secret'),
            $twitter['access_token'],
            $twitter['access_token_secret']
        );
        $tw->setDecodeJsonAsArray(true);
        try {
            $response = $tw->get('account/verify_credentials');
            if (isset($response['errors'])) {
                return $this->responseErrors(config('code.user.login_twitter_failed'), trans('messages.user.login_twitter_failed'));
            }

            $social = SocialNetwork::where('social_id', $response['id_str'])->where('type', config('user.social_network.type.twitter'))->first();
            if ($social) {
                $user = $social->user;
            } else {
                $user = User::create([
                    'name' => $response['name'],
                ]);
                $user->socialNetwork()->create([
                    'social_id' => $response['id_str'],
                    'type' => config('user.social_network.type.twitter'),
                ]);
            }

            $token = JWTAuth::fromUser($user);

            return $this->responseSuccess(compact('token', 'user'));
        } catch (\Exception $e) {
            Log::error('Error when login with twitter: ' . $e->getMessage());
            return $this->responseErrors(config('code.user.login_twitter_failed'), trans('messages.user.login_twitter_failed'));
        }
    }

    public function google(Request $request)
    {
        $idToken = $request->get('id_token');
        if (!$idToken) {
            return $this->responseErrors(config('code.user.login_google_failed'), trans('messages.user.login_google_failed'));
        }

        try {
            $client = new \Google_Client(['client_id' => config('services.google.client_id')]);
            $payload = $client->verifyIdToken($idToken);
            if (!$payload) {
                return $this->responseErrors(config('code.user.login_google_failed'), trans('messages.user.login_google_failed'));
            }

            $social = SocialNetwork::where('social_id', $payload['sub'])->where('type', config('user.social_network.type.google'))->first();
            if ($social) {
                $user = $social->user;
            } else {
                $email = $payload['email'] ?? null;
                $user = $email ? User::firstOrCreate(['email' => $email]) : User::create();
                $user->name = $payload['name'];
                $user->save();
                $user->socialNetwork()->create([
                    'social_id' => $payload['sub'],
                    'type' => config('user.social_network.type.google'),
                ]);
            }

            $token = JWTAuth::fromUser($user);

            return $this->responseSuccess(compact('token', 'user'));
        } catch (\Exception $e) {
            Log::error('Error when login with google: ' . $e->getMessage());
            return $this->responseErrors(config('code.user.login_google_failed'), trans('messages.user.login_google_failed'));
        }
    }
}