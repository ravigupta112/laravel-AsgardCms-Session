<?php namespace Modules\Session\Http\Controllers;

use Cartalyst\Sentinel\Checkpoints\NotActivatedException;
use Cartalyst\Sentinel\Checkpoints\ThrottlingException;
use Cartalyst\Sentinel\Laravel\Facades\Sentinel;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\View;
use Laracasts\Commander\CommanderTrait;
use Laracasts\Flash\Flash;
use Modules\Session\Exceptions\InvalidOrExpiredResetCode;
use Modules\Session\Exceptions\UserNotFoundException;
use Modules\Session\Http\Requests\LoginRequest;
use Modules\Session\Http\Requests\RegisterRequest;
use Modules\Session\Http\Requests\ResetCompleteRequest;
use Modules\Session\Http\Requests\ResetRequest;

/**
 * Class AuthController
 * @package Modules\Session\Http\Controllers
 * @Before("guest", on={"getLogin", "getRegister"})
 */
class AuthController
{
    use CommanderTrait;

    public function __construct()
    {
//        $this->beforeFilter(
//            'guest',
//            array(
//                'only' =>
//                    array('getLogin', 'getRegister')
//            )
//        );
    }

    public function getLogin()
    {
        return View::make('session::public.login');
    }

    public function postLogin(LoginRequest $request)
    {
        $credentials = [
            'email' => $request->email,
            'password' => $request->password
        ];
        $remember = (bool)$request->get('remember_me', false);
        try {
            if ($user = Sentinel::authenticate($credentials, $remember)) {
                Flash::success('Successfully logged in.');
                return Redirect::route('dashboard.index', compact('user'));
            }
            Flash::error('Invalid login or password.');
        } catch (NotActivatedException $e) {
            Flash::error('Account not yet validated. Please check your email.');
        } catch (ThrottlingException $e) {
            $delay = $e->getDelay();
            Flash::error("Your account is blocked for {$delay} second(s).");
        }

        return Redirect::back()->withInput();
    }

    public function getRegister()
    {
        return View::make('session::public.register');
    }

    public function postRegister(RegisterRequest $request)
    {
        $this->execute('Modules\Session\Commands\RegisterNewUserCommand', $request->all());

        Flash::success('Account created. Please check your email to activate your account.');

        return Redirect::route('register');
    }

    public function getLogout()
    {
        Sentinel::logout();

        return Redirect::route('login');
    }

    public function getReset()
    {
        return View::make('session::public.reset.begin');
    }

    public function postReset(ResetRequest $request)
    {
        try {
            $this->execute('Modules\Session\Commands\BeginResetProcessCommand', $request->all());
        } catch (UserNotFoundException $e) {
            Flash::error('No user with that email address belongs in our system.');

            return Redirect::back()->withInput();
        }

        Flash::success('Check your email to reset your password.');

        return Redirect::route('reset');
    }

    public function getResetComplete()
    {
        return View::make('session::public.reset.complete');
    }

    public function postResetComplete($userId, $code, ResetCompleteRequest $request)
    {
        try {
            $this->execute(
                'Modules\Session\Commands\CompleteResetProcessCommand',
                array_merge($request->all(), ['userId' => $userId, 'code' => $code])
            );
        } catch (UserNotFoundException $e) {
            Flash::error('The user no longer exists.');

            return Redirect::back()->withInput();
        } catch(InvalidOrExpiredResetCode $e) {
            Flash::error('Invalid or expired reset code.');

            return Redirect::back()->withInput();
        }

        Flash::success('Password has been reset. You can now login with your new password.');

        return Redirect::route('login');
    }
}