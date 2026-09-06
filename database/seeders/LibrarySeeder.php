<?php

namespace Database\Seeders;

use App\Models\Author;
use App\Models\Book;
use App\Models\BookCategory;
use App\Models\BookIssue;
use App\Models\LibraryMember;
use App\Models\Publisher;
use App\Models\Student;
use Illuminate\Database\Seeder;

class LibrarySeeder extends Seeder
{
    public function run(): void
    {
        $fiction = BookCategory::firstOrCreate(['name' => 'Fiction']);
        BookCategory::firstOrCreate(['name' => 'Biography']);

        $narayan = Author::firstOrCreate(['name' => 'R.K. Narayan']);
        Author::firstOrCreate(['name' => 'APJ Abdul Kalam']);

        $penguin = Publisher::firstOrCreate(['name' => 'Penguin India']);
        Publisher::firstOrCreate(['name' => 'National Book Trust']);

        $book = Book::firstOrCreate(
            ['title' => 'Malgudi Days'],
            ['isbn' => '9780140185226', 'book_category_id' => $fiction->id, 'author_id' => $narayan->id, 'publisher_id' => $penguin->id, 'total_copies' => 3, 'available_copies' => 3]
        );
        Book::firstOrCreate(
            ['title' => 'Wings of Fire'],
            [
                'isbn' => '9788173711466',
                'book_category_id' => BookCategory::where('name', 'Biography')->first()->id,
                'author_id' => Author::where('name', 'APJ Abdul Kalam')->first()->id,
                'publisher_id' => Publisher::where('name', 'National Book Trust')->first()->id,
                'total_copies' => 2,
                'available_copies' => 2,
            ]
        );

        $student = Student::where('admission_no', 'ADM-1001')->first();
        if ($student) {
            $member = LibraryMember::firstOrCreate(
                ['member_type' => 'student', 'member_id' => $student->id],
                ['library_card_no' => 'LIB-0001', 'status' => 'Active', 'max_books' => 3, 'joined_date' => '2026-04-01']
            );

            $issue = BookIssue::firstOrCreate(
                ['book_id' => $book->id, 'library_member_id' => $member->id, 'issue_date' => '2026-07-01'],
                ['due_date' => '2026-07-15', 'status' => 'Issued']
            );
            if ($issue->wasRecentlyCreated) {
                $book->decrement('available_copies');
            }
        }
    }
}
