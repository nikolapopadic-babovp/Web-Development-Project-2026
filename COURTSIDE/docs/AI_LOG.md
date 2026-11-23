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
- ERD review: NOT DONE YET — the six entities and relationships are understandable and consistent.


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

## Milestone 3

### Tools and tasks
- **ChatGPT (OpenAI, [[CHATGPT - 6]]):** generated the first version of the six services, the FlightPHP CRUD routes (`routes/api.php`, `ApiResponder.php`), `Validation.php`, the OpenAPI file (`backend/openapi.json`), the Swagger page, the MapLark service and the first external-API frontend card, added the Photon and Overpass fallback services (`PhotonService.php`, `OverpassService.php`, `SportsDiscoveryService.php`), the venue filter, the neighbourhood/name search and the mocked tests in `backend/tests/`.
- **Claude (claude.ai chat):** reviewed the finished Milestone 3 project against the requirements document and found design problems.
- **Main prompts (summary):**
  - "Implement the business logic and presentation layer for COURTSIDE Milestone 3."
  - "Help me fix the 404 and 'Class UserService not found' errors."
  - "Complete the OpenAPI documentation and the external API requirement; I have a MapLark API key."
  - "MapLark is unavailable, add a free OpenStreetMap fallback."
- **What I kept / changed / rejected:**
Accepted:

I kept the six service classes and FlightPHP CRUD routes, along with the OpenAPI documentation and Swagger interface. I also accepted the use of automated tests to verify the implementation after reviewing the proposed changes.

Changed:

I originally planned to use MapLark for basketball court discovery. However, MapLark returned authorization errors despite having an API key configured.

With AI assistance, I introduced Overpass and Photon as alternative providers. Overpass repeatedly timed out during testing, while Photon eventually returned results successfully.

I also changed the external API feature from general court discovery to searching for basketball courts, sports halls, and other potentially suitable sports venues.

Initially, searches for neighbourhoods such as Grbavica returned unrelated places, including bus stops, pharmacies, markets, and administrative areas. I requested a stricter basketball-specific category filter and separate inputs for venue names and neighbourhoods.

Rejected or not adopted:

I did not adopt the suggested weather API because it was less relevant to my project's intended venue-discovery functionality.

I rejected using unfiltered place-search results because they included irrelevant businesses and locations.

I chose not to share my private API key with AI tools and explicitly prohibited the assistant from reading config.php.

I initially preferred running tests manually after receiving instructions. Later, I authorized automated testing and continued verifying important behavior on my own XAMPP installation.

Where the AI was wrong or produced poor code

1. Missing Composer autoload configuration after adding services

The AI-generated implementation included service classes, but the generated Composer autoloader did not recognize them.

When I opened the API, I received Class "UserService" not found.

I discovered this through the PHP fatal error and corrected it by running composer dump-autoload -o in the backend folder.

2. Missing route imports for Swagger and the external API

The initial project update included routes/docs.php and routes/external.php, but the backend entry point did not load both files.

Swagger and the external API consequently returned {"data":null,"message":"Route not found."}.

After inspecting backend/index.php, I added the required require_once statements before Flight::start(). The endpoints then became accessible.

3. Frontend search button was not initialized

The AI added JavaScript functions for external API venue discovery, but the frontend initially failed to activate the search handler.

The Find courts button therefore appeared unresponsive.

I identified the problem by clicking the button in the browser and reporting that nothing happened. The frontend initialization and event-handling code were corrected.

4. Incomplete sports-halls implementation

One AI-generated patch did not fully implement the new sports-halls functionality. MapLarkService.php was missing the findSportsHalls() method, and the related route was not included.

The automated test produced Call to undefined method MapLarkService::findSportsHalls().

The missing implementation was added and the corresponding tests passed afterward.

5. Unsupported Photon language parameter

The AI-generated Photon request included lang=bs, although the selected public Photon instance did not support that language parameter.

The external API returned HTTP 400.

I noticed the failure while testing and resolved it with AI assistance by removing the unsupported parameter. A regression test was added to ensure it would not be sent again.

6. Irrelevant search results caused by insufficient filtering

The original Photon integration searched by name without enforcing sports-specific categories.

For example, searching for Grbavica returned bus stops, pharmacies, markets, and other unrelated locations.

I identified the issue by examining the actual frontend results. I requested category filtering for basketball pitches, sports halls, and potentially suitable sports centres, as well as separate neighbourhood and venue-name search fields.

7. Incorrect local project directory assumptions

Some AI-generated installation instructions assumed that the backend was located directly under one COURTSIDE folder.

My actual local installation uses C:\xampp\htdocs\Courtside\COURTSIDE\backend.

This initially led to incorrect URLs and 404 errors. I corrected the instructions to match the real directory structure.

Review and verification

PHP syntax validation: 37 backend PHP files passed in the latest isolated review, excluding the private configuration.

JavaScript syntax validation: three files passed.

External API mock tests: 122 checks passed, with no reported failures.

Frontend neighbourhood-search interaction test: passed.

FlightPHP CRUD routes exist for all six database entities.

OpenAPI 3.0.3 specification contains 41 documented operations.

Swagger UI was manually opened successfully.

GET endpoints were tested for the six entities, along with POST, PATCH, and DELETE operations on a test court.

MapLark authorization failures and Overpass timeouts were investigated.

Photon successfully returned real OpenStreetMap results in the frontend.

The latest basketball-specific filtering rules passed offline tests; their complete real-world result coverage has not yet been verified.

Authentication, authorization, JWT middleware, request logging middleware, and complete frontend-to-backend CRUD integration are planned for Milestone 4.