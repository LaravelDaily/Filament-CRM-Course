Next on our list - separating user roles. In our system, we need admins to manage the system settings and employees, while the employees themselves can only manage customers and nothing else:

![](https://laraveldaily.com/uploads/2023/10/employeeViewsCustomerList.png)

In this lesson, we will do the following:

- Create roles Model and Database structure
- Create a user management page (CRUD)
- Add employees to our Customers' table and form for admins to manage
- Add employee changes to our customer history
- Add an additional tab in Customers for `My Customers` - customers assigned to the employee

---

## Creating Roles Model and Database structure

Let's start by creating our migration file:

**Migration**
```php
Schema::create('roles', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->timestamps();
});
```

Then, we can fill out our Model:

**app/Models/Role.php**
```php
class Role extends Model
{
    protected $fillable = ['name'];
}
```

Of course, we should also add some Seeders:

**database/seeders/DatabaseSeeder.php**
```php
use App\Models\Role;

public function run(): void
{
    $roles = [
        'Admin',
        'Employee'
    ];

    foreach ($roles as $role) {
        Role::create(['name' => $role]);
    }
    
    // ...
}
```

That's it for our basic Role setup. We now have a table with two roles - Admin and Employee.

---

## Creating Users Resource

Next on our list - the User management. Let's start by adding a new column to the users' table and relating it to our Role model:

**Migration**
```php
use App\Models\Role;

// ...

Schema::table('users', function (Blueprint $table) {
    $table->foreignIdFor(Role::class)->nullable()->constrained();
});
```

Then, let's add a relationship and a simple `isAdmin` check to our Model:

**app/Models/User.php**
```php
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// ...

protected $fillable = [
    'name',
    'email',
    'password',
    'role_id', // [tl! ++]
];

public function role(): BelongsTo
{
    return $this->belongsTo(Role::class);
}

public function isAdmin(): bool
{
    if (!$this->relationLoaded('role')) {
        $this->load('role');
    }

    return $this->role->name === 'Admin';
}
```

Of course, we should modify our seeders:

**database/seeders/DatabaseSeeder.php**
```php
public function run(): void
{
    $roles = [
        'Admin',
        'Employee'
    ];

    foreach ($roles as $role) {
        Role::create(['name' => $role]);
    }
    
    User::factory()->create([
        'name' => 'Test Admin',
        'email' => 'admin@admin.com',
        'role_id' => Role::where('name', 'Admin')->first()->id, // [tl! ++]
    ]);

    // We will seed 10 employees
    User::factory()->count(10)->create([// [tl! add:start]
        'role_id' => Role::where('name', 'Employee')->first()->id,
    ]);// [tl! add:end]
    
    // ...
}
```

Then, we can finally create our CRUD resource:

```bash 
php artisan make:filament-resource User --generate
```

This has created all the Resource files needed for our User management. Let's modify it:

**app/Filament/Resources/UserResource.php**
```php
use Illuminate\Support\Facades\Hash;

// ...

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';// [tl! --]
    protected static ?string $navigationGroup = 'Settings';// [tl! ++]

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('role_id')
                    ->searchable()// [tl! add:start]
                    ->preload()// [tl! add:end]
                    ->relationship('role', 'name'),
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')
                    ->email()
                    ->required()
                    ->maxLength(255),
                Forms\Components\DateTimePicker::make('email_verified_at'),
                Forms\Components\TextInput::make('password')
                    ->password()
                    ->required()// [tl! --]
                    // https://filamentphp.com/docs/3.x/forms/advanced#auto-hashing-password-field [tl! add:start]
                    ->dehydrateStateUsing(fn(string $state): string => Hash::make($state))
                    ->dehydrated(fn(?string $state): bool => filled($state))
                    ->required(fn(string $operation): bool => $operation === 'create')// [tl! add:end]
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('role.name')// [tl! remove:start]
                    ->numeric()
                    ->sortable(),// [tl! remove:end]
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('role.name')// [tl! add:start]
                    ->sortable(),// [tl! add:end]
                Tables\Columns\TextColumn::make('email_verified_at')// [tl! remove:start]
                    ->dateTime()
                    ->sortable(),// [tl! remove:end]
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
           // ...
    }
    // ... 
}
```

That's it! We can now load the Users page and see our users list:

![](https://laraveldaily.com/uploads/2023/10/usersListExample.png)

---

## Adding Employees to Customers

Next on our list is a requirement for admins to assign employees to customers. This will allow an admin to see which employee is responsible for which customer:

**Migration**
```php
use App\Models\User;

// ...

Schema::table('customers', function (Blueprint $table) {
    $table->foreignIdFor(User::class, 'employee_id')->nullable()->constrained('users');
});
```

Then, in our Customer model, we can add a relationship:

**app/Models/Customer.php**
```php
// ...

protected $fillable = [
    // ...
    'pipeline_stage_id'// [tl! --]
    'pipeline_stage_id',// [tl! add:start]
    'employee_id',// [tl! add:end]
];

// ...


public function employee(): BelongsTo
{
    return $this->belongsTo(User::class, 'employee_id');
}

```

Last, we need to add a field to our Customer form:

**app/Filament/Resources/CustomerResource.php**
```php
use App\Models\Role;
use App\Models\User;

// ...

public static function form(Form $form): Form
{
    return $form
        ->schema([
            Forms\Components\Section::make('Employee Information')// [tl! add:start]
                ->schema([
                    Forms\Components\Select::make('employee_id')
                        ->options(User::where('role_id', Role::where('name', 'Employee')->first()->id)->pluck('name', 'id'))
                ])
                ->hidden(!auth()->user()->isAdmin()),// [tl! add:end]
            Forms\Components\Section::make('Customer Details')
                ->schema([
                    // ...
                ])
                // ...
        ]);
}

public static function table(Table $table): Table
{
    return $table
        ->columns([
            Tables\Columns\TextColumn::make('employee.name')
                ->hidden(!auth()->user()->isAdmin()),
            Tables\Columns\TextColumn::make('first_name')
                ->label('Name')
                // ...
        ])
        // ...
}
```

This gives us a new field on our Customer form:

![](https://laraveldaily.com/uploads/2023/10/employeeField.png)

And once an Employee is selected - we can see that employee in our Customers list:

![](https://laraveldaily.com/uploads/2023/10/employeeList.png)

---

## Adding Employee Changes to Customer History

We need to add our Employee changes to our Customer History, as that is essential information to know in case of some mix-up. So, let's start by adding a new column to our history table:

**Migration**
```php
use App\Models\User;

// ...

Schema::table('customer_pipeline_stages', function (Blueprint $table) {
    $table->foreignIdFor(User::class, 'employee_id')->nullable()->constrained('users');
});
```

Then, we can add a relationship to our History model:

**app/Models/CustomerPipelineStage.php**
```php
// ...

protected $fillable = [
    // ...
    'notes'// [tl! --]
    'notes',// [tl! add:start]
    'employee_id'// [tl! add:end]
];

// ...

public function employee(): BelongsTo
{
    return $this->belongsTo(User::class, 'employee_id');
}
```

Next, we need a way to add this information to our History. We can do this by using an observer on our Customer model:

**app/Models/Customer.php**
```php
public static function booted(): void
{
    self::created(function (Customer $customer) {
        $customer->pipelineStageLogs()->create([
            'pipeline_stage_id' => $customer->pipeline_stage_id,
            'employee_id' => $customer->employee_id,// [tl! ++]
            'user_id' => auth()->check() ? auth()->id() : null
        ]);
    });
    
    self::updated(function (Customer $customer) {// [tl! add:start]
        $lastLog = $customer->pipelineStageLogs()->whereNotNull('employee_id')->latest()->first();

        // Here, we will check if the employee has changed, and if so - add a new log
        if ($customer->employee_id !== $lastLog) {
            $customer->pipelineStageLogs()->create([
                'employee_id' => $customer->employee_id,
                'notes' => is_null($customer->employee_id) ? 'Employee removed' : '',
                'user_id' => auth()->id()
            ]);
        }
    });// [tl! add:end]
}
```

Now, of course, we need to display this information in our History list:

**resources/views/infolists/components/pipeline-stage-history-list.blade.php**
```blade
<x-dynamic-component :component="$getEntryWrapperView()" :entry="$entry"
                     class="grid grid-cols-[--cols-default] fi-in-component-ctn gap-6">
    @foreach($getState() as $pipelineLog)
        <div class="mb-4">
            <div class="">
                <span class="font-bold">{{ $pipelineLog->user?->name ?? 'System' }}</span>, <span x-data="{}" x-tooltip="{
                        content: '{{ $pipelineLog->created_at }}',
                        theme: $store.theme,
                    }">{{ $pipelineLog->created_at->diffForHumans() }}</span>
            </div>
            <div class="">{{-- [tl! remove:start] --}}
                <span class="font-bold">Pipeline Stage:</span> {{ $pipelineLog->pipelineStage->name }}
            </div>{{-- [tl! remove:end] --}}
            <div class="flex flex-col">{{-- [tl! add:start] --}}
                @if($pipelineLog->pipelineStage)
                    <p>
                        <span class="font-bold">Pipeline Stage:</span> {{ $pipelineLog->pipelineStage?->name }}
                    </p>
                @endif
                @if($pipelineLog->employee)
                    <p>
                        <span class="font-bold">Assigned Employee:</span> {{ $pipelineLog->employee?->name }}
                    </p>
                @endif
            </div>{{-- [tl! add:end] --}}
            @if($pipelineLog->notes)
                <div class="">
                    <span class="font-bold">Note:</span> {{ $pipelineLog->notes }}
                </div>
            @endif
        </div>
    @endforeach
</x-dynamic-component>
```

Now, if we update our Customer and assign an employee (or change it) - we should get a log entry like this:

![](https://laraveldaily.com/uploads/2023/10/employeeHistory.png)

That's it, now we have an entire history of our Customer changes.

---

## Limiting Employee Access

Now that we can assign employees and see the History - we should work on our employees' access. Right now, if they were to access the panel - we would see everything:

![](https://laraveldaily.com/uploads/2023/10/employeeAccessSeesTooMuch.png)

To limit this, we will create a few policies:

```bash
php artisan make:policy CustomFieldPolicy --model=CustomField
php artisan make:policy LeadSourcePolicy --model=LeadSource
php artisan make:policy PipelineStagePolicy --model=PipelineStage
php artisan make:policy TagPolicy --model=Tag
php artisan make:policy UserPolicy --model=User
```

Then, we can modify our policies: 

**Note:** We will apply the same code to all policies. We only need the `viewAny()` method at this point

**app/Policies/CustomFieldPolicy.php**
```php
class CustomFieldPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }
}
```

**app/Policies/LeadSourcePolicy.php**
```php
class LeadSourcePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }
}
```

**app/Policies/PipelineStagePolicy.php**
```php
class PipelineStagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }
}
```

**app/Policies/TagPolicy.php**
```php
class TagPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }
}
```

**app/Policies/UserPolicy.php**
```php
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }
}
```

Once this is done - we can refresh our page and see that our Employees have limited access:

![](https://laraveldaily.com/uploads/2023/10/employeeAccessLimited.png)

---

## Adding My Customers Tab

Last on our list - we need to add a tab for our employees to see their customers. We will do this by adding a new tab to our Customers page:

**app/Filament/Resources/CustomerResource/Pages/ListCustomers.php**
```php
public function getTabs(): array
{
    $tabs = [];

    $tabs['all'] = Tab::make('All Customers')
        ->badge(Customer::count());

    if (!auth()->user()->isAdmin()) {// [tl! add:start]
        $tabs['my'] = Tab::make('My Customers')
            ->badge(Customer::where('employee_id', auth()->id())->count());
    }// [tl! add:end]

    // ...

    return $tabs;
}
```

Once this is added, our Customers will see a new tab:

![](https://laraveldaily.com/uploads/2023/10/myCustomersTab.png)

---

In the next lesson, we will modify our Employee creation process to send an invitation to a custom registration page.
