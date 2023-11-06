<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerResource\Pages;
use App\Filament\Resources\CustomerResource\RelationManagers;
use App\Filament\Resources\QuoteResource\Pages\CreateQuote;
use App\Models\Customer;
use App\Models\CustomField;
use App\Models\PipelineStage;
use App\Models\Role;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Infolists\Components\Actions\Action;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\Tabs;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Colors\Color;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Employee Information')
                    ->schema([
                        Forms\Components\Select::make('employee_id')
                            ->options(User::where('role_id', Role::where('name', 'Employee')->first()->id)->pluck('name', 'id'))
                    ])
                    ->hidden(!auth()->user()->isAdmin()),
                Forms\Components\Section::make('Customer Details')
                    ->schema([
                        Forms\Components\TextInput::make('first_name')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('last_name')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('phone_number')
                            ->maxLength(255),
                        Forms\Components\Textarea::make('description')
                            ->maxLength(65535)
                            ->columnSpanFull(),
                    ])
                    ->columns(),
                Forms\Components\Section::make('Lead Details')
                    ->schema([
                        Forms\Components\Select::make('lead_source_id')
                            ->relationship('leadSource', 'name'),
                        Forms\Components\Select::make('tags')
                            ->relationship('tags', 'name')
                            ->multiple(),
                        Forms\Components\Select::make('pipeline_stage_id')
                            ->relationship('pipelineStage', 'name', function ($query) {
                                $query->orderBy('position', 'asc');
                            })
                            ->default(PipelineStage::where('is_default', true)->first()?->id)
                    ])
                    ->columns(3),
                Forms\Components\Section::make('Documents')
                    ->visibleOn('edit')
                    ->schema([
                        Forms\Components\Repeater::make('documents')
                            ->relationship('documents')
                            ->hiddenLabel()
                            ->reorderable(false)
                            ->addActionLabel('Add Document')
                            ->schema([
                                Forms\Components\FileUpload::make('file_path')
                                    ->required(),
                                Forms\Components\Textarea::make('comments'),
                            ])
                            ->columns()
                    ]),
                Forms\Components\Section::make('Additional fields')
                    ->schema([
                        Forms\Components\Repeater::make('fields')
                            ->hiddenLabel()
                            ->relationship('customFields')
                            ->schema([
                                Forms\Components\Select::make('custom_field_id')
                                    ->label('Field Type')
                                    ->options(CustomField::pluck('name', 'id')->toArray())
                                    ->disableOptionWhen(function ($value, $state, Get $get) {
                                        return collect($get('../*.custom_field_id'))
                                            ->reject(fn($id) => $id === $state)
                                            ->filter()
                                            ->contains($value);
                                    })
                                    ->required()
                                    ->searchable()
                                    ->live(),
                                Forms\Components\TextInput::make('value')
                                    ->required()
                            ])
                            ->addActionLabel('Add another Field')
                            ->columns(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function ($query) {
                return $query->with('tags');
            })
            ->columns([
                Tables\Columns\TextColumn::make('employee.name')
                    ->hidden(!auth()->user()->isAdmin()),
                Tables\Columns\TextColumn::make('first_name')
                    ->label('Name')
                    ->formatStateUsing(function ($record) {
                        $tagsList = view('customer.tagsList', ['tags' => $record->tags])->render();

                        return $record->first_name . ' ' . $record->last_name . ' ' . $tagsList;
                    })
                    ->html()
                    ->searchable(['first_name', 'last_name']),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('phone_number')
                    ->searchable(),
                Tables\Columns\TextColumn::make('leadSource.name'),
                Tables\Columns\TextColumn::make('pipelineStage.name'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make()
                        ->hidden(fn($record) => $record->trashed()),
                    Tables\Actions\DeleteAction::make(),
                    Tables\Actions\RestoreAction::make(),
                    Tables\Actions\Action::make('Move to Stage')
                        ->hidden(fn($record) => $record->trashed())
                        ->icon('heroicon-m-pencil-square')
                        ->form([
                            Forms\Components\Select::make('pipeline_stage_id')
                                ->label('Status')
                                ->options(PipelineStage::pluck('name', 'id')->toArray())
                                ->default(function (Customer $record) {
                                    $currentPosition = $record->pipelineStage->position;
                                    return PipelineStage::where('position', '>', $currentPosition)->first()?->id;
                                }),
                            Forms\Components\Textarea::make('notes')
                                ->label('Notes')
                        ])
                        ->action(function (Customer $customer, array $data): void {
                            $customer->pipeline_stage_id = $data['pipeline_stage_id'];
                            $customer->save();

                            $customer->pipelineStageLogs()->create([
                                'pipeline_stage_id' => $data['pipeline_stage_id'],
                                'notes' => $data['notes'],
                                'user_id' => auth()->id()
                            ]);

                            Notification::make()
                                ->title('Customer Pipeline Updated')
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\Action::make('Add Task')
                        ->icon('heroicon-s-clipboard-document')
                        ->form([
                            Forms\Components\RichEditor::make('description')
                                ->required(),
                            Forms\Components\Select::make('user_id')
                                ->preload()
                                ->searchable()
                                ->relationship('employee', 'name'),
                            Forms\Components\DatePicker::make('due_date')
                                ->native(false),

                        ])
                        ->action(function (Customer $customer, array $data) {
                            $customer->tasks()->create($data);

                            Notification::make()
                                ->title('Task created successfully')
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\Action::make('Create Quote')
                        ->icon('heroicon-m-book-open')
                        ->url(function ($record) {
                            return CreateQuote::getUrl(['customer_id' => $record->id]);
                        })
                ])
            ])
            ->recordUrl(function ($record) {
                if ($record->trashed()) {
                    return null;
                }

                return Pages\ViewCustomer::getUrl([$record->id]);
            })
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->hidden(function (Pages\ListCustomers $livewire) {
                            return $livewire->activeTab == 'archived';
                        }),
                    Tables\Actions\RestoreBulkAction::make()
                        ->hidden(function (Pages\ListCustomers $livewire) {
                            return $livewire->activeTab != 'archived';
                        }),
                ]),
            ]);
    }

    public static function infoList(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Personal Information')
                    ->schema([
                        TextEntry::make('first_name'),
                        TextEntry::make('last_name'),
                    ])
                    ->columns(),
                Section::make('Contact Information')
                    ->schema([
                        TextEntry::make('email'),
                        TextEntry::make('phone_number'),
                    ])
                    ->columns(),
                Section::make('Additional Details')
                    ->schema([
                        TextEntry::make('description'),
                    ]),
                Section::make('Lead and Stage Information')
                    ->schema([
                        TextEntry::make('leadSource.name'),
                        TextEntry::make('pipelineStage.name'),
                    ])
                    ->columns(),
                Section::make('Additional fields')
                    ->hidden(fn($record) => $record->customFields->isEmpty())
                    ->schema(
                        fn($record) => $record->customFields->map(function ($customField) {
                            return TextEntry::make($customField->customField->name)
                                ->label($customField->customField->name)
                                ->default($customField->value);
                        })->toArray()
                    )
                    ->columns(),
                Section::make('Documents')
                    ->hidden(fn($record) => $record->documents->isEmpty())
                    ->schema([
                        RepeatableEntry::make('documents')
                            ->hiddenLabel()
                            ->schema([
                                TextEntry::make('file_path')
                                    ->label('Document')
                                    ->formatStateUsing(fn() => "Download Document")
                                    ->url(fn($record) => Storage::url($record->file_path), true)
                                    ->badge()
                                    ->color(Color::Blue),
                                TextEntry::make('comments'),
                            ])
                            ->columns()
                    ]),
                Section::make('Pipeline Stage History and Notes')
                    ->schema([
                        ViewEntry::make('pipelineStageLogs')
                            ->label('')
                            ->view('infolists.components.pipeline-stage-history-list')
                    ])
                    ->collapsible(),
                Tabs::make('Tasks')
                    ->tabs([
                        Tabs\Tab::make('Completed')
                            ->badge(fn($record) => $record->completedTasks->count())
                            ->schema([
                                RepeatableEntry::make('completedTasks')
                                    ->hiddenLabel()
                                    ->schema([
                                        TextEntry::make('description')
                                            ->html()
                                            ->columnSpanFull(),
                                        TextEntry::make('employee.name')
                                            ->hidden(fn($state) => is_null($state)),
                                        TextEntry::make('due_date')
                                            ->hidden(fn($state) => is_null($state))
                                            ->date(),
                                    ])
                                    ->columns()
                            ]),
                        Tabs\Tab::make('Incomplete')
                            ->badge(fn($record) => $record->incompleteTasks->count())
                            ->schema([
                                RepeatableEntry::make('incompleteTasks')
                                    ->hiddenLabel()
                                    ->schema([
                                        TextEntry::make('description')
                                            ->html()
                                            ->columnSpanFull(),
                                        TextEntry::make('employee.name')
                                            ->hidden(fn($state) => is_null($state)),
                                        TextEntry::make('due_date')
                                            ->hidden(fn($state) => is_null($state))
                                            ->date(),
                                        TextEntry::make('is_completed')
                                            ->formatStateUsing(function ($state) {
                                                return $state ? 'Yes' : 'No';
                                            })
                                            ->suffixAction(
                                                Action::make('complete')
                                                    ->button()
                                                    ->requiresConfirmation()
                                                    ->modalHeading('Mark task as completed?')
                                                    ->modalDescription('Are you sure you want to mark this task as completed?')
                                                    ->action(function (Task $record) {
                                                        $record->is_completed = true;
                                                        $record->save();

                                                        Notification::make()
                                                            ->title('Task marked as completed')
                                                            ->success()
                                                            ->send();
                                                    })
                                            ),
                                    ])
                                    ->columns(3)
                            ])
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
            'view' => Pages\ViewCustomer::route('/{record}'),
        ];
    }
}
