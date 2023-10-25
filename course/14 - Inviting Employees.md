This lesson got separated as it's a crucial part of the application - sending out an invitation email to an employee and allowing them to register to the system:

![](https://laraveldaily.com/uploads/2023/10/invitationRegistrationPage.png)

In this lesson, we will do the following:

- Create Invitation Model and Database tables
- Modify UserResource Create button action - to invite the Employee
- Email the invitation to the Employee
- Create a custom page that will be signature (Laravel Signer URL) protected
- Create a custom registration form for the Employee

---

## Create Invitation Model and Database tables

Let's create our migration:

**Migration**
```php
Schema::create('invitations', function (Blueprint $table) {
    $table->id();
    $table->string('email');
    $table->timestamps();
});
```

Then, we can fill our Model:

**app/Models/Invitation.php**
```php
class Invitation extends Model
{
    protected $fillable = [
        'email',
    ];
}
```

As you can see from the setup, it's a pretty basic Model. All we care about - is the email address being invited.

---

## Modify UserResource Create Button Action - to Invite the Employee

Next on our list, we need to modify the User Create button. We don't want to create the User right away. We want to invite them via email first. So let's work on that:

**app/Filament/Resources/UserResource/Pages/ListUsers.php**
```php
use App\Models\Invitation;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Facades\Mail;
use Filament\Notifications\Notification;

// ...

protected function getHeaderActions(): array
{
    return [
        Actions\CreateAction::make(),// [tl! --]
        Actions\Action::make('inviteUser')// [tl! add:start]
            ->form([
                TextInput::make('email')
                    ->email()
                    ->required()
            ])
            ->action(function ($data) {
                $invitation = Invitation::create(['email' => $data['email']]);

                // @todo Add email sending here

                Notification::make('invitedSuccess')
                    ->body('User invited successfully!')
                    ->success()->send();
            }),// [tl! add:end]
    ];
}
```

Once this is done, we will see a different button on our UI:

![](https://laraveldaily.com/uploads/2023/10/userInvitationButtonAndModal.png)

---

## Creating Custom Registration Page

Next, we need to create a custom page where our users will land when they click on the invitation link:

```bash
php artisan make:livewire AcceptInvitation
```

**Note:** We have changed the whole file, so there are no difference indicators.

**app/Livewire/AcceptInvitation.php**
```php
use App\Models\Invitation;
use App\Models\Role;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Form;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Pages\Dashboard;
use Filament\Pages\SimplePage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AcceptInvitation extends SimplePage
{
    use InteractsWithForms;
    use InteractsWithFormActions;

    protected static string $view = 'livewire.accept-invitation';

    public int $invitation;
    private Invitation $invitationModel;

    public ?array $data = [];

    public function mount(): void
    {
        $this->invitationModel = Invitation::findOrFail($this->invitation);

        $this->form->fill([
            'email' => $this->invitationModel->email
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label(__('filament-panels::pages/auth/register.form.name.label'))
                    ->required()
                    ->maxLength(255)
                    ->autofocus(),
                TextInput::make('email')
                    ->label(__('filament-panels::pages/auth/register.form.email.label'))
                    ->disabled(),
                TextInput::make('password')
                    ->label(__('filament-panels::pages/auth/register.form.password.label'))
                    ->password()
                    ->required()
                    ->rule(Password::default())
                    ->dehydrateStateUsing(fn($state) => Hash::make($state))
                    ->same('passwordConfirmation')
                    ->validationAttribute(__('filament-panels::pages/auth/register.form.password.validation_attribute')),
                TextInput::make('passwordConfirmation')
                    ->label(__('filament-panels::pages/auth/register.form.password_confirmation.label'))
                    ->password()
                    ->required()
                    ->dehydrated(false),
            ])
            ->statePath('data');
    }

    public function create(): void
    {
        $this->invitationModel = Invitation::find($this->invitation);

        $user = User::create([
            'name' => $this->form->getState()['name'],
            'password' => Hash::make($this->form->getState()['password']),
            'email' => $this->invitationModel->email,
            'role_id' => Role::where('name', 'Employee')->first()->id
        ]);

        auth()->login($user);

        $this->invitationModel->delete();

        $this->redirect(Dashboard::getUrl());
    }

    /**
     * @return array<Action | ActionGroup>
     */
    protected function getFormActions(): array
    {
        return [
            $this->getRegisterFormAction(),
        ];
    }

    public function getRegisterFormAction(): Action
    {
        return Action::make('register')
            ->label(__('filament-panels::pages/auth/register.form.actions.register.label'))
            ->submit('register');
    }

    public function getHeading(): string
    {
        return 'Accept Invitation';
    }

    public function hasLogo(): bool
    {
        return false;
    }

    public function getSubHeading(): string
    {
        return 'Create your user to accept an invitation';
    }
}
```

Next, we need to modify our View:

**resources/views/livewire/accept-invitation.blade.php**
```blade
<x-filament-panels::page.simple>
    <x-filament-panels::form  wire:submit="create">
        {{ $this->form }}

        <x-filament-panels::form.actions
                :actions="$this->getCachedFormActions()"
                :full-width="true"
        />
    </x-filament-panels::form>
</x-filament-panels::page.simple>
```

Then, all we have to do is add the route:

**routes/web.php**
```php
use App\Livewire\AcceptInvitation;

// ...

Route::middleware('signed')
    ->get('invitation/{invitation}/accept', AcceptInvitation::class)
    ->name('invitation.accept');
```

**Note:** This uses a signed route middleware and Livewire full-page component.

---

## Creating and Sending the Email

As our last step, we will create the email and send it to the User:

```bash
php artisan make:mail TeamInvitationMail
```

Then we can modify the email:

**app/Mail/TeamInvitationMail.php**
```php
use App\Models\Invitation;

// ...

private Invitation $invitation; // [tl! ++]

public function __construct()// [tl! --]
public function __construct(Invitation $invitation)// [tl! ++]
{
    $this->invitation = $invitation; // [tl! ++]
}

public function envelope(): Envelope
{
    return new Envelope(
        subject: 'Team Invitation Mail',// [tl! --]
        subject: 'Invitation to join ' . config('app.name'),// [tl! ++]
    );
}

public function content(): Content
{
    return new Content(
        view: 'view.name',// [tl! --]
        markdown: 'emails.team-invitation',// [tl! add:start]
        with: [
            'acceptUrl' => URL::signedRoute(
                "invitation.accept",
                ['invitation' => $this->invitation]
            ),
        ]// [tl! add:end]
    );
}
```

And, of course, let's create the view file:

**resources/views/emails/team-invitation.blade.php**
```blade
<x-mail::message>
You have been invited to join {{ config('app.name') }}

To accept the invitation - click on the button below and create an account:

<x-mail::button :url="$acceptUrl">
{{ __('Create Account') }}
</x-mail::button>

{{ __('If you did not expect to receive an invitation to this team, you may discard this email.') }}
</x-mail::message>
```

The last step before we try everything out is to send it. Remember the `@todo` that we left? Let's replace it with our email:

**app/Filament/Resources/UserResource/Pages/ListUsers.php**
```php
use App\Mail\TeamInvitationMail;

// ...

protected function getHeaderActions(): array
{
    return [
        Actions\Action::make('inviteUser')
            ->form([
                TextInput::make('email')
                    ->email()
                    ->required()
            ])
            ->action(function ($data) {
                $invitation = Invitation::create(['email' => $data['email']]);

                Mail::to($invitation->email)->send(new TeamInvitationMail($invitation));// [tl! ++]

                Notification::make('invitedSuccess')
                    ->body('User invited successfully!')
                    ->success()->send();
            }),
    ];
}
```

That's it! We can now email invites to people:

![](https://laraveldaily.com/uploads/2023/10/invitationEmailExample.png)

And once they click the link - they will see the registration page:

![](https://laraveldaily.com/uploads/2023/10/invitationRegistrationPage.png)

That's it! Once they fill out the form - they will be redirected to the dashboard with the Employee role:

![](https://laraveldaily.com/uploads/2023/10/invitationRegistrationSuccess.png)

---
