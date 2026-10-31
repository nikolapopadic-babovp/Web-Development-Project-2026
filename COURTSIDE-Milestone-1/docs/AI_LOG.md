# AI Log

## Milestone 1

### Tools and tasks
- **Tool:** ChatGPT (OpenAI; model shown in this session as GPT-6).
- **Claude (chat assistant, claude.ai):** the documentation of this project, advices for overall workflow of the project.
- **Main prompt (summary):** “I want you to make the site with full implementation of the things i sent requested in my project documentation. Fill out the project structure that i made, and create me a SPA shell for the project.”
- **Generated:** responsive SPA shell and view fragments, demo interactions, folder structure, Frontend/Backend/Reviewer custom agents filled out based on request, AI-log skill, README, and draft ERD svg.


### Where the AI was wrong or produced poor code
I had found three issues while reviewing the initial AI-generated draft and corrected them in the current project.

1. **Unknown court/run IDs showed the first sample record.** During code review, `renderCourtDetail()` and `renderRunDetail()` were found to fail back to the first array item for an invalid ID. That mislead me to think that the invalid ID somehow existed, even tho it did in fact not. After fixing the error the functions now render an explicit “not found” state with a link back to the list.
2. **Inline style attributes conflicted with the projects frontend rules.** The generated markup used inline `style` attributes for the court detail image and run capacity bars even though `AGENTS.md` says to use shared CSS and not inline `style` attributes. Now the fixed‑height and progress styles use shared CSS classes; the progress width is calculated from validated data, by a small helper after rendering.
3. **An half-error I didn't have time to fix** Even if it's still not requiered, I find it annoying that in the demo version the guest and user profiles can access the "Admin Preview" section, but I didn't have enough time to finish it and it will be changed in a later milestone. (Still not finnished)

### Review and verification
- Static JavaScript syntax check: passed with `node --check frontend/js/app.js`.
- PHP syntax checks: passed for `backend/index.php` and `backend/config/config.example.php` with `php -l`. //this was an error from my side because i deleted the config file before uploading it to github, but this passed now for the second milestone
- Structure smoke test: passed for required files, 12 view templates and core interaction functions.
- Browser/responsive manual test: NOT DONE YET — record the browser, viewport sizes and features checked on your own XAMPP setup.
- ERD review: NOT DONE YET — confirm the six entities and relationships are understandable and consistent.


## Milestone 2

### Tools and tasks
- **Claude (claude.ai chat, [Sonnet 5.5 Medium]):** generated the first version of `database/courtside.sql`, the `backend/dao/` classes, the `config.example.php` template and the documentation edits (`docs/DATABASE.md`, AGENTS.md and README updates).
  **Main prompt (summary):** "Given the documents that you need to follow last week, and my zipped project until now, I need you to provide me with the update for my project doing the milestone 2 given in the requirements. All the needed info is provided in the project requirements document. Provide me the updates that i need to do for the second milestone and tell me where do i need to implement them."
  **What I kept / changed / rejected:** I kept the database sql file that it gave me and the sample data from it to save me some time. I rejected it's request to fully implement everything on its own to my files and did it step by step modifying some data that i found strange and modifying some lines that were incorrect with their implementation when i checked them with a checkup from another claude conversation from my other account that i use to check if this project is going as planned.

### Where the AI was wrong or produced poor code
1. **Empty configuration template.** `backend/config/config.example.php` from Milestone 1 was an empty file, although the Milestone 1 log said PHP checks passed for it. I noticed it when i started writing this and when i implemented the file now; I replaced it with a template that returns the `db`, JWT and MapLark settings (these two will be done in the later updates).
2. **README out of sync with the repository.** The Milestone 1 README listed `backend/index.php` and `database/erd.svg`, but the repository has no `backend/index.php` and the diagram is `docs/erd.svg`. I noticed it when i found ai being unable to find the locations i provided; I corrected the paths where i found the errors, maybe some are still left wrong.


### Review and verification
- Imported `database/courtside.sql` through phpMyAdmin.
- Verified that all six tables were created successfully.
- Verified that all sample records were inserted.
- PHP syntax validation: passed for all 11 PHP files.
- DAO automated tests: 72 passed, 0 failed.
- Verified CRUD operations for all six entities.
- Verified foreign-key constraints, unique constraints, prepared statements and transaction rollback.