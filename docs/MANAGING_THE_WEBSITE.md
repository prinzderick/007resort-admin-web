# Managing the website

A guide for the people who keep the public website up to date: what each screen is for, how to publish, and what to do when something does not look right. No technical knowledge is needed.

Everything a visitor sees on the website is edited under **Website** in the left-hand menu. If you do not see the **Website** group, your account has no website permissions: ask an administrator.

## The three ideas to remember

1. **Draft, Published, Scheduled, Archived.** New pages, posts, events and albums start as a **Draft**: only staff can see them. **Publish** puts them on the website. **Scheduled** means published with a future date and time: it appears by itself then. **Archive** hides something without deleting it. **Unpublish** takes it back to Draft.
2. **Times are Lagos time.** Every date and time you type or read is Lagos time.
3. **Nothing is lost by accident.** If you change something and try to leave the screen, you are asked first. After every save you see a green message at the bottom right. A red message or a red note under a box says what to fix.

## What each screen does

| Screen | What it controls |
| --- | --- |
| **Website home** | A summary (published posts and events, subscribers, new messages) and shortcuts. |
| **Site settings** | Eight tabs: *Brand and logo*, *Contact and map*, *Opening hours*, *Social links*, *Search and sharing*, *Announcement bar*, *Booking labels*, *Footer*. Change what you need and press **Save settings**; only the tabs you changed are saved, and they go live at once. |
| **Homepage** | The home page is built from blocks: hero slides, highlights, stats, testimonials, FAQs, partners and call-to-action bands. Add, edit, switch on or off, and drag (or use the arrow buttons) to reorder, then press **Save order**. New blocks start **switched off**. |
| **Pages** | The fixed pages: About, House rules, Terms, Privacy, Contact information. Write in the editor with a live preview beside it. |
| **Blog** | News posts, with a cover picture, category, tags, an author, and a publish date and time. **Categories** are on the button at the top. |
| **Events** | Nights, tournaments and special days: dates, venue, price, capacity, and a "Get tickets" link. A weekly event repeats until the date you choose, and the editor lists the next five dates. |
| **Gallery** | Photo albums. Open an album, drag photos in to upload, drag them into order, add captions, choose the album cover, mark favourites, then **Save gallery**. |
| **Media library** | Every uploaded picture. Search, filter by tag, edit the description (alt text) and credit, and delete pictures that are not used. |
| **Subscribers** | People who signed up for news. Export the list (if you have permission), unsubscribe someone, or **erase** them for good. |
| **Messages** | Messages from the contact form. Open one to read it, reply by email, keep a private note, and mark it Read, Replied or Spam. |

## Writing pages, posts and events

The text box uses **Markdown**, a simple way to format text. You do not need to learn it: use the buttons above the box.

| Button | Result |
| --- | --- |
| **B** / **I** | **Bold** and *italic* (select words first) |
| **H2** / **H3** | A heading / a smaller heading |
| **List** / **1. List** / **Quote** | Bullet list, numbered list, quotation |
| **Link** | A link to another page or website |
| **Picture** | A picture from the media library (or upload a new one) |

The **Preview** beside the box (a separate tab on small screens) shows how it will look. Under the text is **Search engines and sharing**: the title and description Google shows, and the picture shown when the link is shared on WhatsApp or Facebook. A preview shows roughly how it will appear; keep titles under about 60 letters and descriptions under about 160.

The **web address** (slug) is made from the title. Change it only if you must; changing it later breaks old links.

## Publishing and scheduling

- On a draft, **Save and publish** makes it live now.
- On a blog post, fill in **Publish date and time** first to **schedule** it. The list shows it as *Scheduled* until then.
- On something already live, **Save changes** updates it, and **Unpublish** or **Archive** take it down.
- Published items cannot be deleted directly: archive them first, then delete.

## Pictures

Anywhere you see **Choose picture**, a window opens with the library. Search, pick, and press **Use this picture**, or upload a new picture right there (drag it into the window). It shows how the picture will be cropped wide, standard and square, so keep the important part near the middle.

- Give every picture a short **description (alt text)**, for example "Guests relaxing by the pool". People using screen readers depend on it and it helps search engines.
- Pictures up to 8 MB in JPEG, PNG, WebP or AVIF are accepted.
- A picture that is still used somewhere cannot be deleted. The delete window lists where it is used so you can replace it there first.

## Opening hours and special days

Under **Site settings, Opening hours**: switch a day to *Closed all day*, or set when it opens and closes. **Use Monday for every day** saves typing. **Add a special day** for holidays that differ from the normal week (Christmas, for example). These hours are shown to visitors; they do not change what can be booked.

## The announcement bar

Under **Site settings, Announcement bar**: switch it on, write the message, choose a style (Information, Promotion or Warning), and optionally a link. The preview shows how it looks. Switch it off to hide it without losing the text.

## Subscribers and privacy

Only **Confirmed** subscribers have agreed by email and can be sent news. **Waiting to confirm** people have not clicked the link yet. **Unsubscribe** stops emails but keeps the address so it is not added again by mistake. **Erase** removes the person completely (for example, a data-deletion request): it cannot be undone. Exporting to CSV opens in Excel; it is logged, so only export when you need to.

## If something goes wrong

| You see | What it means |
| --- | --- |
| "Someone else changed this" | A colleague saved first. Press **Reload the latest version**, then redo your change. |
| "That web address is already used" | Another page, post or event has this slug. Choose a different one. |
| "Archive this first" | Published items must be archived before deletion. |
| "still used on the website" (pictures) | The picture is in use. The list says where. |
| "could not be loaded" | The website service is not reachable right now. Nothing was changed; try again in a minute, or contact IT. |
| A greyed-out or missing button | Your account lacks that permission: *cms.manage* (edit), *cms.publish* (publish), *cms.media.manage* (pictures), *cms.subscribers.view* / *cms.subscribers.export*, *cms.messages.manage*. |

## For administrators

Permissions are assigned to roles under **People, Roles and permissions**. The seeded **Marketing / Website editor** role can edit and manage pictures but not publish; Owner and Manager can do everything. Set `R007_SITE_URL` in the portal's `.env` to the public website address to get the **View on site** and **Preview on site** links.
