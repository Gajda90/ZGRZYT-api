<?php

namespace App\Filament\Resources\Tickets;

use App\Filament\Resources\Tickets\Pages;
use App\Filament\Resources\Tickets\RelationManagers;
use App\Models\Ticket;
use App\Models\User;
use Filament\Actions;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use BackedEnum;

class TicketResource extends Resource
{
    protected static ?string $model = Ticket::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-users';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\Select::make('assigned_it_id')
                    ->label('Przypisany do')
                    ->options(User::whereIn('role', ['it', 'admin'])->pluck('name', 'id'))
                    ->searchable(),
                Forms\Components\TextInput::make('title')
                    ->required()
                    ->maxLength(255)
                    ->columnSpan('full'),
                Forms\Components\RichEditor::make('description')
                    ->required()
                    ->columnSpan('full'),
                Forms\Components\Select::make('status')
                    ->options([
                        'nowe' => 'Nowe',
                        'w trakcie' => 'W trakcie',
                        'zamknięte' => 'Zamknięte',
                    ])
                    ->required(),
                Forms\Components\Select::make('priority')
                    ->options([
                        'niski' => 'Niski',
                        'średni' => 'Średni',
                        'wysoki' => 'Wysoki',
                    ])
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('title')->searchable()->limit(30),
                Tables\Columns\TextColumn::make('user.name')->label('Zgłaszający')->searchable(),
                Tables\Columns\TextColumn::make('assignedTo.name')->label('Przypisany do')->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'nowe' => 'gray',
                        'w trakcie' => 'warning',
                        'zamknięte' => 'success',
                    }),
                Tables\Columns\TextColumn::make('priority')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'niski' => 'gray',
                        'średni' => 'warning',
                        'wysoki' => 'danger',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d-m-Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime('d-m-Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'nowe' => 'Nowe',
                        'w trakcie' => 'W trakcie',
                        'zamknięte' => 'Zamknięte',
                    ]),
                Tables\Filters\SelectFilter::make('priority')
                    ->options([
                        'niski' => 'Niski',
                        'średni' => 'Średni',
                        'wysoki' => 'Wysoki',
                    ]),
            ])
            ->actions([
                Actions\EditAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
    
    public static function getRelations(): array
    {
        return [
            RelationManagers\MessagesRelationManager::class,
        ];
    }
    
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTickets::route('/'),
            'create' => Pages\CreateTicket::route('/create'),
            'edit' => Pages\EditTicket::route('/{record}/edit'),
        ];
    }    
}
