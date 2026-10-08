# Staff manual

The manual's text is `resources/manual/staff-manual.md` (one `## N. Title` per chapter). Two things are made from it:

- **The portal page** (`/help/manual`, menu: Help > Staff manual) renders the markdown at request time. Nothing to build; it works on the online and the property portal alike.
- **The designed PDF** (`resources/manual/SERI-Resort-Staff-Manual.pdf`, served at `/help/manual.pdf`) is built here and committed.

Edit the markdown, then rebuild the PDF (needs Google Chrome and `pdftotext`/`pdfinfo` from poppler):

```bash
cd tools/manual
npm install
npm run build        # MANUAL_EDITION="Edition 2  ·  1 January 2027" npm run build  to change the cover line
```

Look at the result before committing (`pdftoppm -r 55 -png resources/manual/SERI-Resort-Staff-Manual.pdf /tmp/p`). The build fails loudly if a chapter heading is not numbered or its page cannot be found for the contents.
