<?php

namespace App\Filament\App\Widgets;

use App\Filament\App\Resources\Patients\PatientResource;
use App\Filament\App\Resources\Patients\Resources\PatientHistories\PatientHistoryResource;
use App\Models\CalendarAppointment;
use App\Models\PatientHistory;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Guava\Calendar\Enums\CalendarViewType;
use Guava\Calendar\Filament\Actions\CreateAction;
use Guava\Calendar\Filament\Actions\DeleteAction;
use Guava\Calendar\Filament\Actions\EditAction;
use Guava\Calendar\Filament\Actions\ViewAction;
use Guava\Calendar\Filament\CalendarWidget;
use Guava\Calendar\ValueObjects\DateClickInfo;
use Guava\Calendar\ValueObjects\EventClickInfo;
use Guava\Calendar\ValueObjects\FetchInfo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Calendar extends CalendarWidget
{
    protected CalendarViewType $calendarView = CalendarViewType::DayGridMonth;

    protected bool $eventClickEnabled = true;

    protected bool $dateClickEnabled = true;

    protected function getEvents(FetchInfo $info): Collection|array
    {
        $tenantId = Filament::getTenant()?->Id;

        $appointments = CalendarAppointment::query()
            ->where('ClinicId', $tenantId)
            ->where('StartDate', '>=', $info->start)
            ->where('StartDate', '<=', $info->end)
            ->get();

        $patientHistories = PatientHistory::query()
            ->whereHas('patient', fn ($query) => $query->where('ClinicId', $tenantId))
            ->whereNotNull('NextAppointmentDate')
            ->where('NextAppointmentDate', '>=', $info->start)
            ->where('NextAppointmentDate', '<=', $info->end)
            ->with('patient')
            ->get();

        return $appointments->merge($patientHistories);
    }

    public function calendarAppointmentSchema(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('Title')
                ->required()
                ->maxLength(450),
            Textarea::make('Description')
                ->rows(3),
            DateTimePicker::make('StartDate')
                ->required()
                ->label('Start Date'),
            DateTimePicker::make('EndDate')
                ->label('End Date'),
            Checkbox::make('AllDay')
                ->label('All Day')
                ->default(true),
            Select::make('Color')
                ->label('Event Color')
                ->default('#2563eb')
                ->options([
                    '#2563eb' => '🔵  Blue',
                    '#16a34a' => '🟢  Green',
                    '#dc2626' => '🔴  Red',
                    '#9333ea' => '🟣  Purple',
                    '#d97706' => '🟠  Amber',
                ])
                ->native(false),
            Checkbox::make('NotAvailable')
                ->label('Not Available')
                ->helperText('Mark this day as unavailable for appointments.')
                ->default(false),
        ]);
    }

    protected function onDateClick(DateClickInfo $info): void
    {
        $clickedDate = $info->date?->toDateString();

        $notAvailable = CalendarAppointment::query()
            ->where('ClinicId', Filament::getTenant()?->Id)
            ->where('NotAvailable', true)
            ->whereDate('StartDate', $clickedDate)
            ->exists();

        if ($notAvailable) {
            $this->mountAction('notAvailableWarning');

            return;
        }

        $this->mountAction('createCalendarAppointment');
    }

    protected function onEventClick(EventClickInfo $info, Model $event, ?string $action = null): void
    {
        if ($event instanceof PatientHistory) {
            $this->mountAction('visitPatient');

            return;
        }

        if ($action) {
            $this->mountAction($action);
        }
    }

    public function createCalendarAppointmentAction(): CreateAction
    {
        return $this->createAction(CalendarAppointment::class)
            ->mutateFormDataUsing(function (array $data): array {
                $data['ClinicId'] = Filament::getTenant()?->Id;

                return $data;
            })
            ->fillForm(function (?DateClickInfo $info): array {
                return [
                    'StartDate' => $info?->date?->toDateTimeString(),
                    'AllDay' => $info?->allDay ?? true,
                ];
            });
    }

    public function editAction(): EditAction
    {
        return parent::editAction();
    }

    public function viewAction(): ViewAction
    {
        return parent::viewAction()
            ->visible(fn (): bool => ! ($this->getEventRecord() instanceof PatientHistory));
    }

    public function deleteAction(): DeleteAction
    {
        return parent::deleteAction()
            ->requiresConfirmation();
    }

    public function viewPatientHistoryAction(): Action
    {
        return Action::make('viewPatientHistory')
            ->label('View')
            ->icon('heroicon-o-eye')
            ->model(PatientHistory::class)
            ->record(fn () => $this->getEventRecord())
            ->visible(fn (): bool => $this->getEventRecord() instanceof PatientHistory)
            ->action(function (?PatientHistory $record) {
                if (! $record) {
                    return null;
                }

                $tenant = Filament::getTenant();

                return redirect()->to(
                    PatientHistoryResource::getUrl(
                        name: 'edit',
                        parameters: [
                            'record' => $record,
                            'patient' => $record->PatientId,
                        ],
                        tenant: $tenant,
                    )
                );
            });
    }

    public function notAvailableWarningAction(): Action
    {
        return Action::make('notAvailableWarning')
            ->label('Not Available')
            ->icon('heroicon-o-exclamation-triangle')
            ->color('danger')
            ->modalHeading('Doctor Not Available')
            ->modalDescription('This day is marked as unavailable. Appointments cannot be scheduled on this date.')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close');
    }

    public function visitPatientAction(): Action
    {
        return Action::make('visitPatient')
            ->model(PatientHistory::class)
            ->action(function (PatientHistory $record) {
                $tenant = Filament::getTenant();

                return redirect()->to(
                    PatientResource::getUrl(
                        name: 'edit',
                        parameters: ['record' => $record->PatientId],
                        tenant: $tenant,
                    )
                );
            });
    }

    protected function getEventClickContextMenuActions(): array
    {
        return [
            $this->editAction(),
            $this->viewAction(),
            $this->viewPatientHistoryAction(),
            $this->deleteAction(),
        ];
    }

    protected function getActions(): array
    {
        return [
            $this->notAvailableWarningAction(),
        ];
    }

    public function getHeaderActions(): array
    {
        return [
            $this->createAction(CalendarAppointment::class, 'createCalendarAppointmentHeader')
                ->mutateFormDataUsing(function (array $data): array {
                    $data['ClinicId'] = Filament::getTenant()?->Id;

                    return $data;
                })
                ->label('New Appointment'),
        ];
    }
}
