Sometimes, your Customer might get deleted for various reasons, but you might need to recover him months later. This is why we are going to create an `Archived` tab with a `Restore` button:

![](https://laraveldaily.com/uploads/2023/10/customersArchivedTabRestore.png)

In this lesson, we will do the following:

- Add the `Archived` tab to the Customers table
- Add `Delete` button to the table
- Add the `Restore` button to the `Archived` tab
- Disable row click on the `Archived` tab

---

## Adding Delete Button

The first thing to do is add the missing Delete button to our form:

**app/Filament/Resources/CustomerResource.php**
```php
// ...

public static function table(Table $table): Table
{
    return $table
        ->columns([
            // ...
        ])
        ->filters([
            //
        ])
        ->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),// [tl! ++]
           
            // ...
        ])
        ->bulkActions([
           // ...
        ]);
}

// ...
```

That's it. Now we have a delete button in our table:

![](https://laraveldaily.com/uploads/2023/10/customersDeleteButton.png)

---

## Adding Archived Tab

Now that we can delete our customers, we must see them somewhere. Let's add an `Archived` tab to our table:

**app/Filament/Resources/CustomerResource/Pages/ListCustomers.php**
```php
// ...

public function getTabs(): array
{
    $tabs = [];

    $tabs['all'] = Tab::make('All Customers')
        ->badge(Customer::count());

    $pipelineStages = PipelineStage::orderBy('position')->withCount('customers')->get();

    foreach ($pipelineStages as $pipelineStage) {
        $tabs[str($pipelineStage->name)->slug()->toString()] = Tab::make($pipelineStage->name)
            ->badge($pipelineStage->customers_count)
            ->modifyQueryUsing(function ($query) use ($pipelineStage) {
                return $query->where('pipeline_stage_id', $pipelineStage->id);
            });
    }
    
    $tabs['archived'] = Tab::make('Archived')// [tl! add:start]
        ->badge(Customer::onlyTrashed()->count())
        ->modifyQueryUsing(function ($query) {
            return $query->onlyTrashed();
        });// [tl! add:end]

    return $tabs;
}

// ...
```

This will add an `Archived` tab to our table:

![](https://laraveldaily.com/uploads/2023/10/customersArchivedTab.png)

We currently have 2 Customers here, but as you can see - there is an Edit button and a Move to Stage button. We don't want that, so let's hide them on this tab:

**app/Filament/Resources/CustomerResource.php**
```php
// ...

public static function table(Table $table): Table
{
    return $table
        ->columns([
            // ...
        ])
        ->filters([
            //
        ])
        ->actions([
            Tables\Actions\EditAction::make()
                ->hidden(fn($record) => $record->trashed()),// [tl! ++]
            Tables\Actions\DeleteAction::make(),
            Tables\Actions\Action::make('Move to Stage')
                ->hidden(fn($record) => $record->trashed())// [tl! ++]
                ->icon('heroicon-m-pencil-square')
                ->form([
                    // ...
                ])
                ->action(function (Customer $customer, array $data): void {
                    // ...
                }),
        ])
        ->bulkActions([
           // ...
        ]);
}

// ...
```

We have now hidden the Edit and Move to Stage buttons by adding a condition to check if they are `trashed` or not. This gives us the following result:

![](https://laraveldaily.com/uploads/2023/10/customersArchivedTabHiddenButtons.png)

### Disabling Row Actions on Archived Tab

If you visit the `Archived` tab and click on a row, you will get an error like this:

![](https://laraveldaily.com/uploads/2023/10/customersArchivedTabRowClickError.png)

This happens because we have deleted the record previously, but now we are trying to edit it. To prevent this, we can add the following code to our table:

**app/Filament/Resources/CustomerResource.php**
```php
// ...

public static function table(Table $table): Table
{
    return $table
        ->columns([
            // ...
        ])
        ->filters([
            //
        ])
        ->actions([
            // ... 
        ])
        ->recordUrl(function ($record) {// [tl! add:start]
            // If the record is trashed, return null
            if ($record->trashed()) {
                // Null will disable the row click
                return null;
            }

            // Otherwise, return the edit page URL
            return Pages\EditCustomer::getUrl([$record->id]);
        })// [tl! add:end]
        ->bulkActions([
            // ...
        ]);
}

// ...
```

Clicking the row will not do anything in the `Archived` tab but will point to the Edit page in all other tabs.

---

## Adding Restore Button

The last thing to do is add a `Restore` button:


**app/Filament/Resources/CustomerResource.php**
```php
// ...

public static function table(Table $table): Table
{
    return $table
        ->columns([
            // ...
        ])
        ->filters([
            //
        ])
        ->actions([
            Tables\Actions\EditAction::make()
                ->hidden(fn($record) => $record->trashed()),
            Tables\Actions\DeleteAction::make(),
            Tables\Actions\RestoreAction::make(),// [tl! ++]
            Tables\Actions\Action::make('Move to Stage')
                ->hidden(fn($record) => $record->trashed())
                ->icon('heroicon-m-pencil-square')
                ->form([
                    // ...
                ])
                ->action(function (Customer $customer, array $data): void {
                    // ...
                }),
        ])
        ->bulkActions([
           // ...
        ]);
}

// ...
```

This will add the button to the end of the table:

![](https://laraveldaily.com/uploads/2023/10/customersArchivedTabRestoreButton.png)

And if you are wondering why we did not add `->hidden(...)` to the button - Filament handles that for us. If the record is not `trashed` - the button will not be shown:

![](https://laraveldaily.com/uploads/2023/10/customersArchivedTabRestoreButtonHidden.png)

---

That's it. In the next lesson, we will be building a Customer View page.
