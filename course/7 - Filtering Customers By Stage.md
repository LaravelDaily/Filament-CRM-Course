Since our Customer table can have thousands of entries - we need a way to filter them by something. In our case, we will filter them by their Pipeline Stage like this:

![](https://laraveldaily.com/uploads/2023/10/customerTableFilters.png)

In this lesson, we will do the following:

- Create a new filter called `All` to show all Customers
- Dynamically create filters for each Pipeline Stage
- Add counters to each filter to show how many Customers are in each filter

Let's get started!

---

## Creating the Filters

To make filters, we will modify our List file:

**app/Filament/Resources/CustomerResource/Pages/ListCustomers.php** 
```php
use App\Models\Customer;
use App\Models\PipelineStage;
use Filament\Resources\Components\Tab;

// ...

class ListCustomers extends ListRecords
{
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

        return $tabs;
    }
}
```

Once this code is done, we should see tabs appearing above our table:

![](https://laraveldaily.com/uploads/2023/10/customerTableFilters.png)

But what did we do here? Let's look at the code again with some comments:

```php
public function getTabs(): array
{
    $tabs = [];

    // Adding `all` as our first tab
    $tabs['all'] = Tab::make('All Customers')
        // We will add a badge to show how many customers are in this tab
        ->badge(Customer::count());

    // Load all Pipeline Stages
    $pipelineStages = PipelineStage::orderBy('position')->withCount('customers')->get();

    // Loop through each Pipeline Stage
    foreach ($pipelineStages as $pipelineStage) {
        // Add a tab for each Pipeline Stage
        // Array index is going to be used in the URL as a slug, so we transform the name into a slug
        $tabs[str($pipelineStage->name)->slug()->toString()] = Tab::make($pipelineStage->name)
            // We will add a badge to show how many customers are in this tab
            ->badge($pipelineStage->customers_count)
            // We will modify the query to only show customers in this Pipeline Stage
            ->modifyQueryUsing(function ($query) use ($pipelineStage) {
                return $query->where('pipeline_stage_id', $pipelineStage->id);
            });
    }

    return $tabs;
}
```

That's it! This is all we had to do for the filters to work.

---

In the next lesson, we will add an ability to view archived Customers and restore them.
