# AI Log

> **Student review required before submission.** This file records AI assistance for Milestone 1, but the student must verify the wording, add the exact model/tool details visible in their session if needed, and document two genuine cases where AI output was wrong or poor.

## Milestone 1

### Tools and tasks
- **Tool:** ChatGPT (OpenAI; model shown in this session as GPT-6).
- **Claude (chat assistant, claude.ai):** the documentation of this project, advices for overall workflow of the project.
- **Main prompt (summary):** “I want you to make the site with full implementation of the things i sent requested in my project documentation. Fill out the project structure that i made, and create me a SPA shell for the project.”
- **Generated:** responsive SPA shell and view fragments, demo interactions, folder structure, Frontend/Backend/Reviewer custom agents filled out based on request, AI-log skill, README, and draft ERD svg.


### Where the AI was wrong or produced poor code
I had found two issues while reviewing the initial AI-generated draft and corrected them in the current project.

1. **Unknown court/run IDs showed the first sample record.** During code review, `renderCourtDetail()` and `renderRunDetail()` were found to fail back to the first array item for an invalid ID. That mislead me to think that the invalid ID somehow existed, even tho it did in fact not. After fixing the error the functions now render an explicit “not found” state with a link back to the list.
2. **Inline style attributes conflicted with the projects frontend rules.** The generated markup used inline `style` attributes for the court detail image and run capacity bars even though `AGENTS.md` says to use shared CSS and not inline `style` attributes. Now the fixed‑height and progress styles use shared CSS classes; the progress width is calculated from validated data, by a small helper after rendering.
3. **An half-error I didn't have time to fix** Even if it's still not requiered, I find it annoying that in the demo version the guest and user profiles can access the "Admin Preview" section, but I didn't have enough time to finish it and it will be changed in a later milestone.

### Review and verification
- Static JavaScript syntax check: passed with `node --check frontend/js/app.js`.
- PHP syntax checks: passed for `backend/index.php` and `backend/config/config.example.php` with `php -l`.
- Structure smoke test: passed for required files, 12 view templates and core interaction functions.
- Browser/responsive manual test: NOT DONE YET — record the browser, viewport sizes and features checked on your own XAMPP setup.
- ERD review: NOT DONE YET — confirm the six entities and relationships are understandable and consistent.
