<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Enrollment;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo data. Every person here is fictional (RA 10173): names are combined from common
 * Filipino name lists and LRNs start with 9999 so they can never match a real learner.
 * Safe to run more than once.
 */
class DatabaseSeeder extends Seeder
{
    private const FIRST_M = ['Juan', 'Jose', 'Mark', 'John Paul', 'Christian', 'Carlo', 'Miguel', 'Angelo', 'Rafael', 'Joshua', 'Paolo', 'Kenneth', 'Jerome', 'Nathan', 'Emmanuel'];

    private const FIRST_F = ['Maria', 'Ana', 'Carla', 'Kristine', 'Angelica', 'Patricia', 'Jasmine', 'Nicole', 'Bea', 'Camille', 'Joanna', 'Rica', 'Sofia', 'Andrea', 'Mae'];

    private const LAST = ['Dela Cruz', 'Garcia', 'Reyes', 'Santos', 'Mendoza', 'Bautista', 'Villanueva', 'Ramos', 'Aquino', 'Castillo', 'Flores', 'Torres', 'Navarro', 'Lim', 'Gonzales', 'Domingo', 'Salazar', 'Pascual', 'Manalo', 'Soriano', 'Cabrera', 'Valdez', 'Tan', 'Lopez', 'Rivera'];

    public function run(): void
    {
        $password = (string) config('app.demo_password');

        $admin = $this->user('School Admin', 'admin@demo.anecnotes.test', Role::Admin, $password);
        $adviser = $this->user('Maria Santos', 'adviser@demo.anecnotes.test', Role::Teacher, $password);
        $adviser2 = $this->user('Ramon Cruz', 'adviser2@demo.anecnotes.test', Role::Teacher, $password);
        $teacher = $this->user('Liza Navarro', 'teacher@demo.anecnotes.test', Role::Teacher, $password);
        $guidance = $this->user('Jose Reyes', 'guidance@demo.anecnotes.test', Role::Guidance, $password);

        $year = SchoolYear::updateOrCreate(['name' => '2026-2027'], [
            'starts_on' => '2026-06-15', 'ends_on' => '2027-04-02', 'is_current' => true,
        ]);

        $rizal = Section::updateOrCreate(
            ['school_year_id' => $year->id, 'grade_level' => 8, 'name' => 'Rizal'],
            ['adviser_id' => $adviser->id, 'seat_rows' => 6, 'seat_cols' => 6],
        );
        $mabini = Section::updateOrCreate(
            ['school_year_id' => $year->id, 'grade_level' => 9, 'name' => 'Mabini'],
            ['adviser_id' => $adviser2->id, 'seat_rows' => 6, 'seat_cols' => 6],
        );

        $this->enrollSynthetic($rizal, 1, 34, 2012);
        $this->enrollSynthetic($mabini, 101, 32, 2011);

        $this->command->info("Demo accounts ({$admin->email}, {$adviser->email}, {$teacher->email}, {$guidance->email}) use DEMO_PASSWORD.");
    }

    private function user(string $name, string $email, Role $role, string $password): User
    {
        return User::updateOrCreate(['email' => $email], [
            'name' => $name, 'role' => $role, 'password' => $password, 'is_active' => true,
        ]);
    }

    private function enrollSynthetic(Section $section, int $start, int $count, int $birthYear): void
    {
        for ($i = 0; $i < $count; $i++) {
            $n = $start + $i;
            $male = $i % 2 === 0;
            $firsts = $male ? self::FIRST_M : self::FIRST_F;

            $student = Student::updateOrCreate(['lrn' => sprintf('9999%08d', $n)], [
                'last_name' => self::LAST[($n * 7) % count(self::LAST)],
                'first_name' => $firsts[($n * 5) % count($firsts)],
                'middle_name' => self::LAST[($n * 3 + 1) % count(self::LAST)],
                'sex' => $male ? 'M' : 'F',
                'birthdate' => sprintf('%d-%02d-%02d', $birthYear, ($n % 12) + 1, ($n % 27) + 1),
            ]);

            Enrollment::updateOrCreate(['section_id' => $section->id, 'student_id' => $student->id], [
                'seat_row' => intdiv($i, $section->seat_cols) + 1,
                'seat_col' => ($i % $section->seat_cols) + 1,
                'status' => 'active',
                'enrolled_on' => '2026-06-15',
            ]);
        }
    }
}
