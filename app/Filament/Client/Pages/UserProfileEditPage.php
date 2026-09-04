<?php

namespace App\Filament\Client\Pages;

use App\Models\UserExperience;
use App\Models\UserProfile;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

/**
 * @property Form $form
 */
class UserProfileEditPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $title = 'Ubah Profil';

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.client.pages.user-profile-edit-page';

    public ?array $data = [];

    public UserProfile $userProfile;

    public function mount(): void
    {
        $userProfile = auth()->user()->userProfile;

        $this->userProfile = $userProfile;

        $data = $userProfile->toArray();

        $data['work_experiences'] = auth()->user()->userExperiences->toArray();

        $this->form->fill($data);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make(['default' => 1, '2xl' => 3])
                    ->schema([
                        Grid::make()
                            ->columnSpan(2)
                            ->schema([
                                Section::make('Data Profil')
                                    ->schema([
                                        Forms\Components\Textarea::make('description')
                                            ->label('Deskripsi Singkat')
                                            ->required()
                                            ->rows(3)
                                            ->maxLength(400),
                                        Forms\Components\Grid::make(['default' => 1, 'xl' => 3])
                                            ->schema([
                                                Forms\Components\TextInput::make('birth_place')
                                                    ->label('Tempat Lahir')
                                                    ->required()
                                                    ->maxLength(255),
                                                Forms\Components\Grid::make(['default' => 1, 'xl' => 3])
                                                    ->columnSpan(['default' => 1, 'xl' => 2])
                                                    ->schema([
                                                        Forms\Components\Select::make('birth_date_day')
                                                            ->label('Tanggal Lahir')
                                                            ->options(function () {
                                                                $day = [];
                                                                for ($n = 1; $n <= 31; $n++) {
                                                                    $day[$n] = $n;
                                                                }
                                                                return $day;
                                                            })
                                                            ->required(),
                                                        Forms\Components\Select::make('birth_date_month')
                                                            ->label('Bulan')
                                                            ->options(function () {
                                                                return [
                                                                    1 => 'Januari',
                                                                    2 => 'Februari',
                                                                    3 => 'Maret',
                                                                    4 => 'April',
                                                                    5 => 'Mei',
                                                                    6 => 'Juni',
                                                                    7 => 'Juli',
                                                                    8 => 'Agustus',
                                                                    9 => 'September',
                                                                    10 => 'Oktober',
                                                                    11 => 'November',
                                                                    12 => 'Desember',
                                                                ];
                                                            })
                                                            ->required(),
                                                        Forms\Components\Select::make('birth_date_year')
                                                            ->label('Tahun')
                                                            ->options(function () {
                                                                $day = [];
                                                                for ($n = 1990; $n <= now()->year - 14; $n++) {
                                                                    $day[$n] = $n;
                                                                }
                                                                return $day;
                                                            })
                                                            ->required(),
                                                    ]),
                                            ]),
                                        Forms\Components\Grid::make(['default' => 1, 'xl' => 3])
                                            ->schema([
                                                Forms\Components\Select::make('gender')
                                                    ->label('Jenis Kelamin')
                                                    ->options([
                                                        'L' => 'Laki-Laki',
                                                        'P' => 'Perempuan',
                                                    ])
                                                    ->required(),
                                                Forms\Components\TextInput::make('body_weight')
                                                    ->label('Berat Badan (kg)')
                                                    ->numeric()
                                                    ->required()
                                                    ->maxLength(255),
                                                Forms\Components\TextInput::make('body_height')
                                                    ->label('Tinggi Badan (cm)')
                                                    ->numeric()
                                                    ->required()
                                                    ->maxLength(255),
                                            ]),
                                        Forms\Components\Grid::make(['default' => 1, 'xl' => 3])
                                            ->schema([
                                                Forms\Components\TextInput::make('ktp_address')
                                                    ->label('Alamat Sesuai KTP')
                                                    ->required()
                                                    ->maxLength(255)
                                                    ->columnSpan(['default' => 1, 'xl' => 2]),
                                                Forms\Components\Select::make('ktp_city_id')
                                                    ->label('Kota Sesuai KTP')
                                                    ->relationship('ktpCity', 'title')
                                                    ->required(),
                                            ]),
                                        Forms\Components\Grid::make(['default' => 1, 'xl' => 3])
                                            ->schema([
                                                Forms\Components\TextInput::make('domisili_address')
                                                    ->label('Alamat Tempat Tinggal Sesuai Domisili')
                                                    ->required()
                                                    ->maxLength(255)
                                                    ->columnSpan(['default' => 1, 'xl' => 2]),
                                                Forms\Components\Select::make('domisili_city_id')
                                                    ->label('Kota Tempat Tinggal Domisili')
                                                    ->relationship('ktpCity', 'title')
                                                    ->required(),
                                            ]),
                                        Forms\Components\Grid::make(['default' => 1, 'xl' => 3])
                                            ->schema([
                                                Forms\Components\Select::make('education_degree_id')
                                                    ->label('Jenjang Pendidikan')
                                                    ->relationship('educationDegree', 'title')
                                                    ->required(),
                                                Forms\Components\Select::make('religion_id')
                                                    ->label('Agama')
                                                    ->relationship('religion', 'title')
                                                    ->required(),
                                                Forms\Components\Select::make('ethnic_group_id')
                                                    ->label('Suku Asal')
                                                    ->relationship('ethnicGroup', 'title')
                                                    ->required(),
                                            ]),
                                        Forms\Components\Checkbox::make('skck_status')
                                            ->label('Memiliki SKCK'),
                                        Forms\Components\Checkbox::make('surat_kesehatan_status')
                                            ->label('Memiliki Surat Keterangan Sehat'),
                                    ]),
                                Section::make('Data Pengalaman Bekerja')
                                    ->schema([
                                        Forms\Components\Repeater::make('work_experiences')
                                            // ->relationship('userExperiences')
                                            ->label('Pengalaman Bekerja')
                                            ->addActionLabel('Tambahkan Pengalaman Bekerja')
                                            ->reorderable()
                                            ->reorderableWithButtons()
                                            ->reorderableWithDragAndDrop(false)
                                            ->orderColumn('sort_order')
                                            ->schema([
                                                Grid::make(['default' => 1, 'xl' => 3])
                                                    ->schema([
                                                        Forms\Components\TextInput::make('experience_year')
                                                            ->label('Tahun Bekerja')
                                                            ->required(),
                                                        Forms\Components\TextInput::make('experience_job_name')
                                                            ->label('Nama Profesi, Jabatan, atau Posisi')
                                                            ->required(),
                                                        Forms\Components\TextInput::make('experience_description')
                                                            ->label('Nama Tempat Bekerja')
                                                            ->required(),
                                                        Checkbox::make('is_current_job')
                                                            ->label('Sedang bekerja di sini?')
                                                            ->fixIndistinctState(function (Get $get) {
                                                                return $get('is_current_job') === true;
                                                            }),
                                                    ])
                                            ])
                                    ])
                            ]),
                        Grid::make()
                            ->columnSpan(1)
                            ->schema([
                                Section::make('Data Lainnya')
                                    ->columnSpan(2)
                                    ->schema([
                                        Forms\Components\FileUpload::make('profile_picture_path')
                                            ->label('Foto Profil')
                                            ->required()
                                            // ->disk('public')
                                            ->directory('profile-picture')
                                            ->visibility('public')
                                            ->image()
                                            ->imageResizeMode('cover')
                                            ->imageCropAspectRatio('3:4')
                                            // ->panelAspectRatio('3:4')
                                            ->imagePreviewHeight('450')
                                            ->imageEditor()
                                            ->imageEditorAspectRatios([
                                                '3:4',
                                            ])
                                            ->imageResizeTargetWidth('675')
                                            ->imageResizeTargetHeight('900')
                                            ->openable()
                                            ->downloadable(),
                                    ])
                            ])
                    ])
            ])
            ->statePath('data')
            ->model($this->userProfile);
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label(__('filament-panels::resources/pages/edit-record.form.actions.save.label'))
                ->submit('save'),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $data['birth_date'] = Carbon::createFromDate(year: $data['birth_date_year'], month: $data['birth_date_month'], day: $data['birth_date_day']);

        unset($data['birth_date_day']);
        unset($data['birth_date_month']);
        unset($data['birth_date_year']);

        $userExperiences = $data['work_experiences'];
        unset($data['work_experiences']);

        UserProfile::updateOrCreate(['user_id' => auth()->user()->id], $data);

        UserExperience::query()
            ->whereBelongsTo(auth()->user())
            ->delete();

        foreach ($userExperiences as $userExperience) {
            $userExperienceModel = new UserExperience();
            $userExperienceModel->user_id = auth()->user()->id;
            $userExperienceModel->experience_year = $userExperience['experience_year'];
            $userExperienceModel->experience_job_name = $userExperience['experience_job_name'];
            $userExperienceModel->experience_description = $userExperience['experience_description'];
            $userExperienceModel->sort_order = $userExperience['sort_order'];
            $userExperienceModel->is_current_job = $userExperience['is_current_job'];
            $userExperienceModel->save();
        }

        Notification::make()
            ->success()
            ->title(__('filament-panels::resources/pages/edit-record.notifications.saved.title'))
            ->send();
    }
}
