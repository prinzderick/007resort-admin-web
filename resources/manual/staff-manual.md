## 1. How to use this manual

This manual shows every person at SERI Resort how to do their own job on the system, one step at a time. You do not need to read all of it: read chapters 2 and 17, then your own chapter.

| If you are a... | Read chapters |
| --- | --- |
| Waiter or bartender | 2, 5, 17, 18 |
| Cashier (restaurant, cafe, supermarket, spa, salon) | 2, 6, 17, 18 |
| Reception cashier | 2, 6, 7, 17, 18 |
| Supervisor | 2, 6, 8, 17, 18 |
| Kitchen or bar screen staff | 2, 9, 18 |
| Sports Entrance or Sports Store attendant | 2, 10, 18 |
| Storekeeper or procurement | 2, 11 |
| Accountant | 2, 12, 17 |
| Manager | 2, 3, 4, 13, 17 |
| Owner or IT administrator | 2, 3, 14, 18 |
| Marketing or website editor | 2, 15 |
| Anyone helping customers who book online | 16 |

Every role chapter is laid out the same way: **Your job**, **Your device**, **Start of shift**, **During the shift**, **End of shift**, **Never do this**, and **If something goes wrong**.

Words in **bold** are the exact names of buttons and screens as you see them. If your screen looks slightly different, the steps are still the same.

This manual was written from the system as built on 8 October 2026. Where a rule can be changed by the manager in the admin portal, the chapter says so. Where something still needs confirming, it is marked **Check with IT**.

## 2. Quick start for everyone

Everyone signs in as themselves with a staff number and PIN, and follows the same seven golden rules. This chapter covers both.

### The devices

| Device | Who uses it | What it is for |
| --- | --- | --- |
| Waiter tablet (Android) | Waiters, bartenders | Take orders, send to kitchen or bar, print the bill, collect payment at the table |
| Supervisor tablet (Android) | Supervisors | Approve or reject voids, discounts, comps and price changes |
| Cashier POS (Windows PC) | Cashiers, Reception | Sell, take payment, confirm waiter collections, receive cash, bookings |
| Kitchen or bar screen (KDS) | Kitchen and bar staff | See incoming tickets and move them to Ready |
| Sports Entrance / Sports Store tablet | Gate and store attendants | Scan QR tickets, release and return equipment |
| Admin portal (web browser) | Manager, accountant, owner, IT, marketing | Reports, staff, prices, setup, website |

The admin portal is at `https://admin.seriresorts.com`. Open it on a computer, not on the tablets.

### Signing in

1. Type your **Staff number** and your **PIN**, then press **Sign in**. (On the POS the PIN pad is on screen.)
2. Or tap your staff card on the reader, then type your PIN. A card alone never signs you in.
3. If you do not have a PIN, use **Use password instead** and type your username and password.
4. Waiter tablets then ask you to **Check out this tablet**: pick the facility where you work this shift and press **Check out tablet**.

Five wrong tries lock your account for 15 minutes. If that happens, see a supervisor.

### Locking and signing out

- A tablet locks itself after 5 minutes without use and shows **Tablet locked**. Type your PIN to continue, or press **Not me - sign out** if it is not yours.
- The POS locks after 15 minutes and asks you to sign in again.
- The kitchen screen locks after 2 minutes but stays visible. Tap any button to sign in.
- Always sign out when you leave your station.

### The seven golden rules

1. **Sign in as yourself.** Never use someone else's PIN or card. Everything you do is recorded under your name.
2. **A waiter never marks a bill paid.** A waiter records what was collected; a cashier confirms it.
3. **Printing the bill freezes the order.** Nothing can be added, removed or discounted until a supervisor reopens the bill.
4. **Sent items cannot be changed.** To add more, start a new order on the same table.
5. **Voids, discounts, comps and price changes need a supervisor's approval** unless your role is allowed to do them.
6. **Count cash with the other person watching.** Cash handovers and shift counts are always counted in front of both people.
7. **If the connection drops, do not repeat the action.** Read the banner on the screen first (chapter 18).

## 3. Roles at a glance

What you can do on the system depends on your role, and the system enforces it: a button you are not allowed to use is hidden or refused. The table lists the 12 standard roles.

| Role | Main job | Can do | Cannot do, or needs approval |
| --- | --- | --- | --- |
| Owner | Runs the business | Everything. Signing in from outside the property needs a second code (MFA) | Nothing is blocked, but every action is recorded |
| Manager | Runs the property | Staff, prices, setup, reports, and everything a supervisor can do | Cannot give a role higher than their own |
| Accountant | Money and reports | Reconcile settlements, financial reports, approve refunds and reversals, view cash handovers | Cannot sell, take payment or change prices |
| Cashier | Takes payment | Sell, take and split payment, settle orders and tabs, run the cash drawer, confirm waiter collections, receive cash handovers | Voids, refunds, comps, price changes and reopening a bill need a supervisor |
| Waiter | Serves tables | Take orders, send to kitchen, serve, print the bill, record payment collected at the table, hand cash to the cashier | Cannot mark a bill paid. Cancelling a printed bill needs a supervisor |
| Bartender | Serves drinks | Same as a waiter for bar orders, and moves bar tickets | Same limits as a waiter |
| Kitchen staff | Cooks | See kitchen tickets, move them along, mark an item unavailable | Cannot take orders or see money |
| Storekeeper | Stock | Receive, transfer and count stock, release and return rented equipment | Stock adjustments need a supervisor's approval |
| Unit supervisor | Floor authority | Approve voids, discounts, comps and price changes, sign off cash variances, close another person's cash session, operational reports | Cannot approve their own requests |
| Procurement | Buying | Suppliers and purchase receipts | Nothing on sales or cash |
| IT administrator | Systems | Devices, sessions, security events, audit log, connection settings | Never finance, pricing or approvals |
| Marketing | Website | Edit and publish website pages, blog, events, gallery, read the contact inbox and subscribers | Nothing on sales, staff or setup |

**Execute and approve.** Some actions have two levels. A person who may *execute* an action (for example a cashier asking for a void) needs a person who may *approve* it. Supervisors, managers and the owner approve voids, discounts, comps and price changes. The accountant also approves refunds and reversals. Nobody can approve their own request.

The manager can create custom roles and change what a role may do (admin portal, **Roles & permissions**), so your property may differ slightly from this table.

## 4. Facilities at a glance

Each facility has its own way of taking orders and being paid, set by the manager in the admin portal. The table shows the setup at launch; ask your manager if yours was changed.

| Facility | What happens there | How it is paid | Food and drinks come from |
| --- | --- | --- | --- |
| Main Reception | Bookings, tickets, memberships, equipment rental, counter sales | Pay first at the desk. Cashier must have an open cash session. Pays for Pool, Sports and Pool Bar visits | n/a |
| Restaurant | Table service, customer tabs | Pay after service (the order must be served first) | Main Kitchen; drinks from Restaurant Bar |
| Indoor Club | Table service, tabs, member discounts | Tab, paid when the customer leaves | Main Kitchen; Club Bar |
| Beauty Spa | Appointments in 3 treatment rooms, memberships | Paid at the facility, cash session required | n/a |
| Salon (Male, Female) | Appointments, 3 and 4 chairs | Paid at the facility, cash session required | n/a |
| Pool Area | Entry with QR ticket | Tickets are bought at Reception | n/a |
| Pool Bar | Drinks and food at the pool, tabs | Paid at Reception | Pool Bar |
| Bush Bar | Table service, tabs | Pay before leaving | Main Kitchen; Bush Bar |
| Event Centre | Events for up to 300, tickets, tabs | Paid at the desk or on a tab | Event Bar |
| Cafe, Cyber Cafe | Counter sales; Cyber Cafe has 10 bookable PCs in 30-minute slots | Cash session required | Cafe Barista |
| Sports Arena | Football pitch, 2 lawn tennis courts, basketball court in 60-minute slots | Booked and paid at Reception | n/a |
| Sports Store | Rented equipment | Paid at Reception, released against the QR ticket | n/a |
| Supermarket, Super Store | Retail with barcode scanning | Cash session required | n/a |
| Main Kitchen, Smaller Kitchen | Cook for the restaurant, club, bars, cafe and event centre | n/a | n/a |

**Three ways payment works.**

- **Pay first:** the customer pays before anything is sent or prepared.
- **Pay after service:** the order is sent, prepared and served, then paid. The cashier can only take payment on a *served* order.
- **Tab:** many orders are added to one customer tab and settled once, in one payment, at the end.

Your screen follows the facility you are working in, so you do not need to remember which is which. If a button is missing, it is usually because that facility does not use it.

## 5. Waiter and bartender (tablet)

**Your job:** take orders at the table or bar, send them to the kitchen or bar, serve them, print the bill and record the money you collect. You never mark a bill as paid; the cashier does that.

**Your device:** the waiter tablet.

### Start of shift

1. Sign in with your staff number and PIN (chapter 2).
2. On **Check out this tablet**, pick where you are working this shift and press **Check out tablet**. The tablet is now recorded against you and that facility.
3. If it says **No facilities are assigned to you**, ask a supervisor. If it says the tablet is already checked out, the last person did not return it; ask IT or a manager to check it in.

### Taking an order

1. Tap a table. For a customer without a table, use **Customers / tabs**, press **New**, type **Customer name or lounger / seat** and press **Start order**.
2. Press **Open table** or **Take order**. Pick a category on the left and tap items to add them.
3. In the item sheet choose any extras or required options, type a note if needed (for example *no pepper*), then press **Add to cart**.
4. Check the cart. **Estimated total** is only an estimate: final prices and tax are set by the server.
5. Press **Send order**. Sending locks the items. If the customer wants more later, add it as a **new order** on the same table or tab.

Each line shows its progress: ROUTED, ACCEPTED, IN PROGRESS, READY. When food or drink is ready the tablet plays a sound and shows **Order ready to serve**. Serve it, then press **Mark served**.

### Voids, discounts and comps

- **Void order:** if you may void, press it and confirm. If the button says **Void (supervisor)**, a supervisor types their **Supervisor staff number** and PIN and presses **Authorise**.
- **Adjust** an item: choose *Discount %*, *Discount amount*, *Price override* or *Complimentary (comp)*, type the **Reason (required)** and press **Apply**.
- If you are not allowed, the tablet says **Sent to a supervisor for approval** and the order shows **Waiting for a supervisor to approve**. The order is locked until they decide.

### The bill and collecting payment

1. When the customer asks for the bill, press **Print bill**. The tablet says **Bill sent to the printer**. **The order is now frozen.**
2. The bill panel shows **Bill printed, awaiting payment** with *Amount due*, *Confirmed*, *Pending cashier* and *Remaining*. If you may not print bills, it says to ask the cashier.
3. Press **Take payment**. **Amount to pay now** is filled in with what remains. Part payments are fine; the rest can use another method.
4. Pick how the customer pays:
   - **Cash:** type the amount and the cash received. The tablet shows **Change to give**. Press **Record cash collected**.
   - **Card machine:** charge the customer on the bank machine, then type the **Approval code** from the slip (last 4 digits and slip reference are optional).
   - **Transfer:** show the account on screen, which is only for this bill, and ask the customer to transfer exactly the amount. Type a **Bank reference** if you have one.
   - **Pay link:** the tablet shows a QR code and link. Wait for **PAID - Confirmed by the payment provider**, then press **Done**.
5. After cash, card machine or transfer the tablet says **Recorded. Waiting for the cashier to confirm - the bill is not paid until then.** Tell the cashier. They check the slip, alert or cash and confirm it.

Pay link and transfer need a connection. Printing the bill and handing over cash also need the network.

### Your cash

Tap the wallet icon, **My cash**, to see **Cash in hand**, your limit, **Pending cashier** and **Collections today**. Some facilities do not allow waiters to hold cash: then **My cash** is hidden and the tablet says **Cash goes to the cashier**.

To hand over: press **Hand over to the cashier**, count the money, type it in **Cash you are handing over (count it)**, add a note if you like and press **Hand over to cashier**. Walk to the cashier, who counts it in front of you. The result shows *Received*, *SHORT* or *OVER*.

If you reach your limit, the tablet says **Hand over your cash first**. Press **Hand over now**, then take the payment.

### End of shift

1. Make sure every order is sent and every collection is recorded.
2. Hand over your cash.
3. Open the menu and choose **Return tablet (end of shift)**, then **Return tablet**. Use **Sign out (keep tablet)** only if you are coming back.

### Never do this

- Never tell a customer a bill is paid before the cashier confirms it.
- Never take payment again for a bill the tablet shows as already collected.
- Never keep cash above your limit or overnight.
- Never lend your tablet while you are signed in.

### If something goes wrong

See chapter 18: banners such as **Reconnecting to the server**, orders marked **PENDING CONFIRMATION**, and what to say to the supervisor.

## 6. Cashier (POS)

**Your job:** take payment, confirm what waiters collected, receive their cash, and keep your cash drawer correct.

**Your device:** the cashier POS (Windows). The tabs along the side depend on your facility and your permissions: **Sell**, **Tables & tabs**, **Reception**, **Collected by waiters**, **Cash handover**, **Approvals**, **Cash session**, **History** and **Queue**. Only **Queue** is always there. The top bar shows **Online**, or **OFFLINE: server unreachable**.

### Start of shift: open your cash session

1. Sign in with your staff number and PIN.
2. Open **Cash session**. It says **No cash session is open** until you start one.
3. Type **Opening float (cash already in the drawer)** and press **Open cash session**. You see **Cash session opened.**

You cannot take or confirm cash without an open session: the POS says **Open a cash session before taking cash.**

### Selling at the counter

1. In **Sell**, pick a category and tap items, use **Search items**, or scan a barcode into **Scan barcode / SKU (then Enter)**.
2. Quick notes such as *No ice* or *Takeaway* can be tapped before adding an item.
3. Check the cart: *Subtotal*, *Discount*, *Tax* and **TOTAL**. If the POS is offline it shows **ESTIMATE** instead; the server prices it later.
4. At a facility with a kitchen or bar, press **Send**. At **pay after service** facilities, press **Mark served** when it is served. If it says **Some items are still being prepared**, wait.
5. Press **Pay**.

### Taking payment

1. In **Take payment**, check **Amount to pay now**. A smaller amount makes it a partial payment.
2. Pick the **Payment method**: **Cash**, **POS terminal** or **Bank transfer**. For cash, type the **Cash handed over**; the system works out the change.
3. For a terminal or transfer, type the **Terminal / transfer reference**.
4. To split between methods, press **Add tender**, add each part, and watch **Entered** and **Still to enter** until nothing is left.
5. Customer pays on their phone instead? Choose the pay-link, type the **Customer email** and press **Create pay-link**. When the customer says they have paid, press **Check payment**.
6. You see **Payment recorded.** and the receipt prints. A reprint is marked DUPLICATE.

The same card or transfer reference cannot be used twice.

### Tabs

1. In **Tables & tabs**, press **Open a new tab**, type the **Customer name** and optionally the **Table**, then **Open tab**.
2. Each round is an order in **Sell**. Press **Send** for each round.
3. At the end, send the current order, then press **Settle tab**. This opens **Take payment** for the whole balance.

### Confirming what waiters collected

Open **Collected by waiters**. **Money below is NOT in the till yet.** Check the slip, bank alert or cash against each line.

- **Match:** press **Confirm**, type the reference you see (optional), then **Confirm payment**. The order is settled and the receipt prints. Confirming cash needs your own open cash session.
- **Does not match:** press **Reject**, type a reason of at least 3 characters and press **Reject and alert supervisor**. The bill stays unpaid and a supervisor is alerted.

You cannot confirm money you collected yourself. Pay-link and per-bill transfer payments are confirmed by the payment provider, not by you. Unconfirmed collections expire on their own after 30 minutes and the supervisors are alerted.

### Receiving a waiter's cash

1. Open **Cash handover**. A waiter's handover appears under **Cash handovers from waiters**.
2. Press **Count and receive**. It shows what the waiter **Declared**. Count the notes with the waiter watching, type the **Counted amount (naira)** and press **Record count**.
3. The result is *Matches the declared amount*, *SHORT by...* or *OVER by...*. A difference over N500 needs a supervisor to press **Sign off variance**. You cannot sign off a handover you received.

You cannot receive your own handover.

### Bills, voids, discounts and comps

- **Print bill** prints an 80 mm bill marked NOT A RECEIPT. The order is now frozen (**BILL PRINTED**). To change it, press **Reopen bill**; this needs a supervisor.
- **Void order**, **Discount**, **Comp**, **Price**, **Reopen bill**, **Refund** and **Reverse** all open the approval box. Type the **Reason** (it is kept in the audit trail) and the value, then choose one:
  1. Tick **A supervisor is here** and let them type their staff number and PIN on your POS: it is approved at once.
  2. Leave it unticked and press **Submit**: the request goes to the supervisors' devices and your screen waits. If nobody answers in 10 minutes, check **Approvals** later.

### History: reprint, refund, reverse

Open **History**. Pick a payment, then **Reprint** a receipt, **Refund** (enter the amount; part or all) or **Reverse** (a same-session correction such as the wrong method; the order becomes payable again). Refunds and reversals need supervisor authorisation.

### End of shift: close your cash session

1. Open **Cash session**. Count the drawer. The system figure is hidden until you close (a blind count).
2. Type **Counted cash** and an optional note, then press **Close cash session**.
3. You see *Declared*, *system* and *variance*. Press **Print shift report** to keep a copy.
4. Sign out.

### Never do this

- Never take payment on a bill the POS says is **COLLECTED BY WAITER**: confirm or reject it in **Collected by waiters** instead.
- Never confirm a collection you have not checked against the slip, alert or cash.
- Never leave a cash session open overnight.
- Never process an offline cash payment as if it were final (chapter 18).

### If something goes wrong

See chapter 18. The **Queue** screen shows anything waiting to be confirmed.

## 7. Reception

**Your job:** sell court bookings, spa and salon appointments, pool tickets, equipment rentals and memberships at the desk, and hand the customer a QR ticket.

**Your device:** the cashier POS, **Reception** tab. Everything in chapter 6 (cash session, payment, receipts) applies too. Reception pays for the Pool, Sports Arena and Pool Bar, so most customers pay here.

**Bookings need a live connection.** A slot is scarce, so a booking can never be saved offline. If the POS is offline, wait for the connection.

### Booking a slot and taking payment

1. Open **Reception**. Under **What to book**, pick the court, pitch, room or PC.
2. Pick the day with the **<** and **>** buttons and the number of **People** with **-** and **+**.
3. Tap a time slot. Each slot shows if it is open, full or has places left, and its price.
4. Type the **Customer name**, or press **Find a member** to link a member. A walk-in does not need a member record: a name is enough.
5. Press **Hold this slot**. The POS says **Slot held. Add rentals if needed, then take payment before the hold expires.** The hold runs out after 15 minutes, so do not leave it.
6. For equipment, press an item under **Add equipment rental**. You see **Added <item>.**
7. Press **Take payment and print QR ticket** and take payment as in chapter 6. You see **Booking confirmed.**
8. Hand over the printed QR ticket. It says **Scan at the gate. One use per ticket.** A group gets one QR per person.

If the customer changes their mind, press **Release hold**.

### If the slot or ticket has a problem

| You see | What to do |
| --- | --- |
| **That slot was just taken. Pick another.** | Choose another slot. Nothing was charged. |
| **The hold expired. Start the booking again.** | Hold the slot again. |
| **Payment taken, but the ticket QR could not be issued** | The money is safe. Open **History** and reissue the ticket from there. |
| **Bookings need a live connection to the server** | Wait until the POS shows **Online**, then try again. |

### Memberships

Members are signed up at Reception or buy online. **Find a member** links an existing member to a booking. Plans, prices, discounts and visit limits are set up by the manager in the admin portal (chapter 13). The admin portal screen **Memberships** only shows members; it does not create them.

### Online bookings

Customers also book on the website. If a customer shows a reference or QR ticket from the website, see chapter 16.

### Never do this

- Never hand over a ticket before the payment shows **Payment recorded.**
- Never promise a slot you have not held.
- Never let a hold expire with the customer standing there: release it or pay for it.

## 8. Supervisor

**Your job:** be the second pair of eyes. You approve or reject sensitive requests, sign off cash differences, and keep the floor moving. You can never approve your own request.

**Your devices:** the supervisor tablet, the **Approvals** tab on the POS, and **Approvals** in the admin portal.

### What needs your approval

| Request | When it reaches you |
| --- | --- |
| Void a sent order | Always. Voiding an unsent draft needs no approval unless the facility rule says so |
| Comp (free item) | Always |
| Price override | Always |
| Discount | Above the facility's limit (N10,000 at Reception, N5,000 at the restaurant, club, spa and bush bar at launch), or always if the facility lists it |
| Refund or reversal of a payment | Above the facility's limit, or always at limit 0 |
| Cancel or reopen a printed bill | Always, and printing again after a cancel also needs you |
| Stock adjustment, attendance clock correction | When requested by the storekeeper or staff |

A paid order cannot be voided: it needs a refund or reversal first.

### Approving on the supervisor tablet

1. Open the **Approvals** tab. The badge shows how many are waiting. **Live orders** shows every order (filter **Awaiting approval**), and **Tables & tabs** shows what is occupied.
2. Each card shows **Requested by <name>**, what is asked and the reason.
3. Press **Approve** or **Reject**. The box is titled **Approve: <summary>** or **Reject: <summary>**. Type a note (a reason is wise when rejecting), type **Your PIN** and confirm.
4. The waiter's order unlocks straight away.

### Approving at the till (in person)

When someone ticks **A supervisor is here**, you type your **Supervisor staff number** and **Supervisor PIN** on their screen. It approves that one action at once. Your sign-in is valid for 5 minutes and one use, and it is recorded.

On the POS **Approvals** tab you can also press **Approve** or **Reject** on any waiting request, with an optional note.

### Before you approve, check

1. Who asked, and for what item and amount?
2. Is the **Reason** believable and written?
3. Is the customer or the situation really there? Look at the table.
4. Is it within what you allow? When unsure, reject and ask the manager.

### Cash differences

When a cashier counts a waiter's cash and the difference is more than N500, it waits for you. On the POS open **Cash handover** and press **Sign off variance**, add a note and confirm. You cannot sign off a handover you received yourself.

You can also close another cashier's cash session if they have left.

### Alerts to watch

- A rejected waiter collection alerts you at once: find out why the slip or cash did not match.
- A collection that expired after 30 minutes without confirmation alerts you. Expired cash stays the waiter's responsibility until it is sorted.

### Never do this

- Never approve from memory of a conversation: read the request.
- Never give your PIN to anyone so they can "approve" for you.
- Never approve a comp or price change for yourself or a friend.
- Never sign off a variance you did not witness being counted.

### If something goes wrong

**Approvals** needs a connection. If the tablet shows **Reconnecting**, you cannot decide until it is back, and nobody can send a new request either, because approvals are never saved offline. See chapter 18.

## 9. Kitchen and bar screens (KDS)

**Your job:** cook or pour what comes in, and tell the waiter it is ready by moving the ticket along.

**Your device:** the kitchen or bar display screen. Each screen shows only the tickets for its own station: food lines go to the kitchen screen and drink lines to the bar screen.

### First time on a screen (IT does this once)

1. IT gives a one-time registration code (admin portal, **Devices**).
2. On the screen, type a **Screen name** and the **Registration code**, then press **Register this screen**.
3. Pick **Which station is this screen?** The choice is remembered. You can change it later from the menu.

If the screen says it is **no longer registered**, ask IT for a new code.

### Start of shift

1. Tap any button. The **Staff sign-in** screen asks for your **Staff number, or tap your card**, then your PIN. A card alone does not sign you in.
2. Some accounts see **View only**. They can watch the board but not move tickets.

The screen locks after 2 minutes without use. It stays visible and live, but you must sign in to press anything.

### The board

There are three columns: **New**, **In progress** and **Ready**. Each ticket shows the table, the order number, the waiter, the items with extras and notes, and a timer.

- The timer turns **amber at 5 minutes** and **red at 10 minutes**. You can change these in the menu under **Ticket colours (minutes)**.
- A chime sounds for each new ticket. The sound can be turned off in the menu.
- The small status shows **Live**, **Syncing...**, **Reconnecting...**, **Server degraded** or **Signed out**.

### Moving a ticket

Press the button on the ticket. It changes as you go:

1. **Accept** when you have seen it.
2. **Start** when you begin cooking or pouring.
3. **Ready** when it is done. The waiter's tablet plays a sound and shows **Order ready to serve**.
4. **Served** when it has left the pass. The ticket disappears from the board.

If another screen moved the ticket first, you see **Ticket #n was just updated. Check it and tap again.**

Cancelled tickets leave the board by themselves.

### The menu (the three-line button)

**Change station**, **Lock screen**, **Sign out**, **Ticket colours (minutes)** with **Reset to defaults**, and the sound switch.

### Never do this

- Never press **Ready** before the food or drink is really ready: the waiter will serve it.
- Never leave the screen signed in and unattended.

### If something goes wrong

If you see a red banner **RECONNECTING - showing last known board**, the screen is showing old information and **cannot send changes**. It refreshes every 10 seconds. Keep working from the paper or call the waiters, and do not press buttons until it says **Live** again: the kitchen screen never saves changes for later.

If you see **Your account is not allowed to update tickets**, you have a view-only account: tell your supervisor.

To mark an item sold out (86), check with IT whether your screen offers it; otherwise ask a manager to switch the item's availability off in the admin portal (chapter 13).

## 10. Sports Entrance and Sports Store attendants

**Your job:** at the gate, check that a QR ticket is real and allow entry. At the store, hand out and take back the equipment a customer paid for.

**Your device:** a tablet set up by IT as **Sports Entrance scanner** or **Sports Store**. You sign in with your own staff number and PIN; the ticket is what the scan checks. Scans always need a live connection: the tablet never guesses.

### Sports Entrance

1. Open **Sports Entrance**. Point the camera at the customer's QR code. If the camera is not working, type the code in the field and press **Check**, or use a handheld scanner.
2. Read the full-screen result:

| Result | What it means | What you do |
| --- | --- | --- |
| **VALID** (with *Valid until...*) | A good ticket | Let them in |
| **ALREADY USED** | This ticket was used before | Do not let them in. Send them to Reception |
| **EXPIRED** | The ticket's time has passed | Do not let them in. Send them to Reception |
| **NOT YET VALID** (with *Valid from...*) | Too early | Ask them to come at the stated time |
| **WRONG FACILITY** | Ticket is for another place | Direct them to the right place |
| **CANCELLED** | The booking was cancelled | Do not let them in |
| **STAFF APPROVAL** | A supervisor must decide | Call a supervisor |
| **NOT RECOGNISED** | Not a SERI Resort ticket | Do not let them in |
| **NO CONNECTION** | The tablet cannot reach the server | Press **Try again**. Do not guess |

The system decides the result. The **What you do** column is the recommended house rule: your supervisor can change it.

3. Press **Scan next**. **Recent scans** lists what you scanned this session.

A group booking has one QR per person. A screenshot of the QR works.

### Sports Store

1. Open **Sports Store**. Press **Scan or type entitlement QR**, or **Next customer**.
2. The screen says **This is exactly what was paid for / rented:** and lists the items.
3. **Releasing:** tick the items you hand over and press **Release selected (n)**. Items show RELEASED.
4. **Returning:** tick the items that came back, set the **Condition** (OK, **Damaged** or **Lost**) and press **Record return (n)**. Items show RETURNED.
5. If the screen says **This entitlement is CANCELLED. Do not release anything.**, do not hand out anything.

The system blocks releasing the same item twice and shows you its message.

### Never do this

- Never let someone in or hand out equipment when the tablet shows **NO CONNECTION**.
- Never release more than the screen lists.
- Never skip recording **Damaged** or **Lost**: the store's record must show what really came back.

### If something goes wrong

Scans and equipment releases are never saved for later. If the tablet is offline, wait for the connection or ask a supervisor to decide while you note the customer's booking reference. See chapter 18.

## 11. Storekeeper and procurement

**Your job:** keep stock records true. The storekeeper receives, moves and counts stock. Procurement manages suppliers and purchase receipts.

**Your device:** a computer or tablet browser, admin portal, **Inventory** menu. You see only the screens your role allows; the rest are greyed out with the message **Needs permission**.

### Where things are

| Screen | What it is for | Who |
| --- | --- | --- |
| **Stock** | Balances per location: *Location*, *Item*, *On hand*, *Reorder at*, *Updated*. **Export CSV** saves them | Storekeeper, manager |
| **Stock** receive and purchase receipt forms | Add stock that arrived. Scan or type the item code, add each line | Storekeeper, procurement |
| **Transfers** | Move stock between locations (for example Main Store to a facility store): **New transfer** | Storekeeper |
| **Counts** | Stock-take: **New count**, enter *Counted* against *Expected*, then **Post count** | Storekeeper |
| **Adjustments** | Correct stock after loss or damage: **Adjust stock**, with a *Kind* and a *Reason* | Storekeeper requests, supervisor approves |
| **Suppliers** | The supplier list: **Add supplier** with contact, phone and email | Procurement |

### Receiving stock

1. Open **Stock** and start the receive form. Pick the **Location**.
2. For each item, scan the barcode or type the item code, then enter the quantity (**Add a line** for more items).
3. Check the lines against the delivery note and submit.

### Doing a stock count

1. Open **Counts** and press **New count**. Pick the location.
2. Count the shelf, then type what you found under **Counted**. The screen shows the **Variance** against **Expected**.
3. Press **Post count**. Only people allowed to post counts see it work; others see **You do not have permission to post counts.**

### Correcting stock

Press **Adjust stock**, choose the location, kind and lines, and type the **Reason**. A supervisor must approve it before the balance changes. It shows under **Adjustments** as pending until then.

### Things to know

- Stock leaves the shelf when an order is **sent** (at some facilities, when it is paid). A sale of something with no stock is refused.
- Transfers and counts are recorded, never deleted. Fix a mistake with a correcting entry, not by hiding the first one.
- The Sports Store releases and takes back rented equipment on the tablet (chapter 10), not here.

### Never do this

- Never adjust stock to hide a shortage. Record the reason truthfully.
- Never post a count you did not do yourself.

**Check with IT:** the exact names of the submit buttons on the receive and transfer forms, so this chapter can be tightened after a walk-through on the live portal.

## 12. Accountant

**Your job:** make sure the money recorded matches the money received, approve refunds and reversals, and produce the reports the owner relies on. You cannot sell or take payment.

**Your device:** a computer, admin portal at `https://admin.seriresorts.com`, **Finance** menu.

### The Finance screens

| Screen | Use it to |
| --- | --- |
| **Payments** | Find any payment and open its detail |
| **Refunds & reversals** | See money handed back. Large ones wait for your approval |
| **Collected by waiters** | See money waiters collected that a cashier has not yet confirmed |
| **Cash handovers** | See what waiters handed to cashiers, including **Cash in hand** per waiter |
| **Cash sessions** | Review each cashier's shift: float, expected cash, counted cash, difference |
| **Settlements** | Match card and transfer money against Paystack and bank settlements. There is a verify action for Paystack |
| **Reports** | Sales by day, facility, shift and transaction |

The **Dashboard** gives a quick view: net sales, transactions, orders, refunds, payments by channel and **Needs attention**.

### Daily routine

1. Open **Reports**, choose **Day**, pick yesterday and press **Apply**.
2. Read **Facilities on <date>**: *Orders*, *Gross*, *Discounts*, *Net sales*, *Refunds* and *Tickets*, with the **Property total**. Click a facility to drill down to shift and cashier, then the transaction. **Export CSV** to keep a copy.
3. Open **Cash sessions** and look at every shift with a difference.
4. Open **Collected by waiters**: anything still waiting for confirmation, or expired, needs a conversation with the cashier or supervisor.
5. Open **Approvals**: decide refunds and reversals waiting for you.
6. Open **Settlements** and reconcile what Paystack and the bank paid out.

For a month, use **Period revenue** (set *Period from* and *to*) to see revenue by facility, by payment method (captured, refunded, net) and by operating point.

### Approving a refund or reversal

A cashier cannot refund by themselves; the request waits in **Approvals** (and on supervisors' devices). Check the payment, the reason and the amount, then approve or reject.

- A **refund** gives money back and does **not** reopen the order.
- A **reversal** cancels a payment and **does** reopen the order; it is only allowed within 24 hours.
- Money records can never be edited or deleted: every correction is a new entry.

**Check with IT:** Paystack refunds are currently only *recorded* for you to action manually in the Paystack dashboard. They are not sent to Paystack automatically.

### Never do this

- Never approve a refund you cannot match to a payment.
- Never reconcile a settlement without checking the Paystack or bank figure.
- Never share your admin sign-in.

## 13. Manager

**Your job:** run the property day to day: people, prices, facility rules and the numbers. You hold everything a supervisor can do, so you can approve and sign off.

**Your device:** a computer, admin portal at `https://admin.seriresorts.com`. You can also use the POS or a tablet like a supervisor.

### Your daily routine

1. **Morning:** open the **Dashboard**. Read **Needs attention**, **Stock alerts** and the **Setup progress** card.
2. **Approvals:** clear anything waiting (the badge on **Approvals** shows how many).
3. **During the day:** watch **Orders**, **Tables** and **Bookings** (read-only views of what the tablets, POS and kitchen screens record). Check **Collected by waiters** for money waiting for a cashier.
4. **Evening:** check **Cash sessions** for differences and read the day's sales in **Reports** (chapter 12 explains how).

### Setup jobs

These all live under the admin portal's **Setup** menu. Changes apply to new activity straight away; orders already open keep their old prices.

**Add or change a product and price**

1. **Catalog & prices**, then **Add product**. Fill in **Name**, **SKU** (must be unique, for example FD-JOL-CH), **Category**, **Kind** and the standard **Price**. Optionally set **Prepared by** (kitchen or bar), **Tax rate**, **Sold at** and **Track stock**. Press **Create product**.
2. To change a price, open the product and press **Set a new price**: give the **New price**, **Applies to**, **Facility** and **Starts on** (empty means now). The old price ends automatically.
3. **Where it is sold** lets you sell the item at another facility, with its own price if you like.

**Mark an item sold out**

Open the product, find **Where it is sold**, and switch availability off for that facility with a reason such as *Sold out*. Staff then see that reason when they try to sell it.

**Change a facility rule**

1. **Facilities**, open the facility, open its **Rules** tab.
2. Change the control you want. Only rules for features switched on under the **Capabilities** tab appear. **Reset to default** returns a control to the standard value.
3. Press **Save rules**.

The rules you will use most:

| Rule | What it controls |
| --- | --- |
| Payment timing | Pay first, pay after service, or tab |
| Approval limit and which actions need approval | What reaches a supervisor |
| Cash session required | Whether cash needs an open drawer |
| Waiter cash collection, and the cash limit | Whether waiters may hold cash and how much |
| Allow offline orders, allow offline payments | What a terminal may do without the server (none, cash only, all) |
| Booking hold time | How long a held slot waits for payment |

**Waiter cash setting, per person**

Open the person in **Staff**, card **Cash collected at tables**. **For this person** is *Inherit* the facility's rule, *Allow* or *Deny*. **Personal cash limit** sets how much they may hold before they must hand over. A waiter who may not hold cash sends cash customers to the cashier.

**Membership plans**

**Membership plans**, then **Add plan**: **Name**, **Code**, **Lasts (days)**, **Price**, **Member discount**, **Visit limit**, **Guests per visit**, **Book ahead**, **Grace after expiry**, **Renewal reminder**, **Valid at** and **On sale**. Members who already bought keep their original terms.

**Business and receipts**

**Business & receipts** holds the business profile, the text printed on receipts and the VAT setting.

**Add a facility**

Use **Add a facility**. The wizard asks for a template, a name, the capabilities, starting rules, operating points and a final review. The facility code cannot be changed afterwards.

### Staff, devices and roles

Creating staff, setting PINs, granting roles and registering devices is the same for managers and the owner: see chapter 14.

### Never do this

- Never change a price in the middle of service without telling the cashiers.
- Never give a role higher than your own; the system will not let you.
- Never turn on offline payments for card or transfer: they cannot be checked offline and the system does not queue them.

## 14. Owner and IT administrator

**Your job:** the owner decides who may do what and sees everything. The IT administrator keeps devices, sign-ins and connections working; IT never handles finance, pricing or approvals. Managers can do the staff and device jobs below too.

**Your device:** a computer, admin portal at `https://admin.seriresorts.com`. The owner signs in from outside the property with an extra code (MFA).

### Add a staff member and give them a way to sign in

1. **People > Staff > Add staff member**.
2. Fill in **First name**, **Last name** and **Staff number** (printed on their badge, for example S-0014). Phone and email are optional. Press **Create staff member**.
3. On their page, in the **Sign-in** card, set at least one of:
   - **New PIN** (4 to 8 digits, used on tablets and POS) then **Set PIN**.
   - **New password** (at least 8 characters) then **Set password**.
   - **NFC card**: tap the card on a reader or type its number, then **Assign card**. A card always needs the PIN too.
4. In **Roles and scopes**, choose the **Role**, then **Applies to** (the whole organisation, a site, or one facility) and which one, then **Grant role**.
5. Check that **Status** is **Active**.

The staff list shows which sign-ins each person has.

### Reset a PIN or password

Open the person's page and set a new one. There is no old-PIN question and no recovery link, so be sure you are speaking to the right person. A locked account (five wrong tries) frees itself after 15 minutes.

### When someone leaves, or a card is lost

- Set the person's **Status** to **Suspended** or **Ended**. Use **Remove card** for a lost card.
- If a tablet or POS is lost, open **People > Devices** and press **Revoke** on it. It is cut off even if someone is still signed in.

### Register a new tablet, POS or kitchen screen

1. **People > Devices > Register a device**. Optionally choose the **Home facility**, then press **Issue registration code**.
2. A one-time code appears with its expiry. Type it on the new device. It works once.
3. After the device connects, use **Edit** on its row to set the home facility, mode and operating point. This applies the next time it connects.

If a device says the code was refused (a 422 message), it was already used or has expired: issue a new one. A tablet that says **Tablet role not resolved** needs **Reset tablet enrolment** and a new code.

Fingerprint or face terminals are registered under the same screen with their serial number, name, facility and protocol; their token is shown once. Card machines are under **Card machines > Add card machine**.

### Pointing devices at a server

A device talks to one server at a time: the **online server** (on the internet) or the **property server** (inside the building). The tablet asks for the address the first time (**Connect to the resort server**).

To switch later without losing the tablet's enrolment, tap the title on the sign-in or lock screen **seven times quickly** to open **Connection**. Type the PIN if one was set, pick the server or type another address and press **Switch**. Each server keeps its own enrolment and sign-in, so switching back needs no new code. It refuses to switch while offline records are waiting; let them send first.

If the property server is switched off, nobody can sign in on devices that use it. Use the online server until it is back.

### The database tool (IT only)

When it is installed, the database tool is reached only through a secure tunnel from your own computer, never from the public internet. Steps are in the VPS runbook of the infrastructure repository. Take a backup before editing any row, and prefer the read-only login for looking around.

### Keeping watch

- **Audit log** (System menu) lists who changed what, with CSV export.
- **Sync & IT** shows whether the property server and the online server are in step.
- Sign-in security events (repeated failures, rejected collections) show up for the supervisors and for you.

### Never do this

- Never share the owner or admin sign-in, and never write PINs on devices.
- Never leave a leaver's account **Active**.
- Never power off a machine you suspect was compromised: disconnect it from the network and call whoever manages security.
- Never publish demo accounts or demo PINs on a live server.

## 15. Marketing and website editor

**Your job:** keep the public website fresh: pages, blog, events, gallery and the homepage, and answer the contact inbox.

**Your device:** a computer, admin portal, **Website** menu. It is hidden entirely if your role has no website permissions. Editing needs the edit permission, and publishing needs the publish permission, which IT staff do not have.

### How content works

- Everything starts as a **Draft**. **Publish** makes it live. A scheduled item appears by itself at its date. **Archive** hides it without deleting. A published item must be archived before it can be deleted.
- All times are Lagos time.
- If you see **Someone else changed this**, reload to get the latest version and redo your change.

### The Website screens

| Screen | What it does |
| --- | --- |
| **Site settings** | Eight tabs: Brand, Contact, Opening hours, Social, SEO, Announcement bar, Booking labels, Footer. **Save settings** puts changes live at once. The brand name shown across the site is edited here |
| **Homepage** | Built from blocks. New blocks start switched off. Reorder them, then **Save order** |
| **Pages**, **Blog**, **Events** | A text editor with buttons and a live preview. Blog and Events need a title, a web address (slug) and search (SEO) text |
| **Gallery** | Albums. Save with **Save gallery** |
| **Media library** | Upload JPEG, PNG, WebP or AVIF pictures up to 8 MB. A picture still in use cannot be deleted |
| **Messages** | Contact-form messages. Mark them **Read**, **Replied** or **Spam** and add private notes |
| **Subscribers** | Newsletter list. Export to CSV (logged). Unsubscribe or erase on request; erasing is permanent |

### Publish a blog post

1. **Website > Blog**, add a post. Type the title, web address and SEO text, and write the body.
2. Add a picture from the **Media library**.
3. Press **Preview**, read it, then **Publish**. Or set a date to publish by itself.

### Change the brand name or contact details

Open **Site settings**, **Brand** or **Contact**, change the text and press **Save settings**. No developer is needed.

### Never do this

- Never publish prices or offers the facilities have not agreed.
- Never erase a subscriber unless they asked: it cannot be undone.
- Never upload someone else's photo without the right to use it.

## 16. Helping customers who book or buy online

Customers can book courts, spa and salon slots, buy pool tickets and memberships on the website at `https://seriresorts.com`, with or without an account. Reception staff are the people they call, so this chapter says what the customer sees and what to do.

### What customers can do

- Book a court, treatment or slot, **Hold this slot**, pay by Paystack and get a **QR ticket**.
- Buy pool day tickets (up to 20 per order, one QR per person) and memberships.
- Check out as a **guest** with only name, email and phone, or register an account (password of at least 10 characters, or Google or Facebook sign-in when enabled).
- Use **Find my booking** with the booking reference plus the email or phone used to book.
- Signed-in customers see **My bookings**, can cancel or reschedule when allowed, and download or print their tickets.

### What to tell a customer who calls

| The customer says | What to do |
| --- | --- |
| "I lost my ticket." | Ask for the **reference** from the confirmation page or email. Use **Find my booking** (reference plus email or phone), or press **Send it again** to resend the ticket. A screenshot of the QR also works |
| "The payment says it is taking longer than usual." | Say it will confirm shortly and they have not been charged twice. Ask them to wait a few minutes, then use **Find my booking** |
| "My slot expired while I was paying." | The slot is held for **10 minutes**. If someone else took it nothing is charged. If it expired during payment the site says the team has been told and will refund: take their reference and raise it with the cashier or accountant |
| "I want to cancel." | Online cancellation is only offered while the booking policy allows it. The page shows **Free cancellation until <time>** and **Refund if cancelled now**. Refunds go back to the original payment method |
| "I want a different time." | Only signed-in customers can reschedule online. A guest booking must be cancelled and rebooked, or handled by you at Reception |
| "The site says nothing has been charged, contact reception." | The booking system was unreachable. Help them book at the desk (chapter 7) |
| "It says please wait a minute." | Too many lookups or logins in a short time. Ask them to wait a minute and try again |

### Looking up a booking yourself

In the admin portal use **Bookings**, **Tickets & entry**, **Payments** and **Memberships** (read-only screens) to see what a customer bought and whether it was paid.

### At the gate

The QR ticket shows its valid-from and valid-until time. The gate scanner checks it (chapter 10). Rented equipment is paid for at the venue; the customer shows the booking QR at the Sports Store.

### Never do this

- Never promise a refund before you have checked the payment.
- Never ask a customer for their Paystack or bank details: the site never needs them from you.

**Check with IT:** the booking reference on the Find my booking page shows an example that begins with the old brand (`007-`). Confirm what a real reference looks like after the rename, and update this chapter.

## 17. Money rules on one page

Money is only real once a cashier or the payment provider has confirmed it, every correction is a new entry, and every cash count is witnessed. Print this page and keep it at each till.

### The life of an order

1. **Draft:** items are being added. Unsent items can still be removed.
2. **Sent:** the kitchen or bar has it. Items are locked.
3. **In preparation, then Ready:** the kitchen screen moves it along.
4. **Served:** the waiter marks it served.
5. **Paid (settled):** payment confirmed, receipt printed.

An order can be **voided** at any step before it is paid. A paid order needs a refund or reversal first.

### How money moves at a table

1. Waiter prints the bill. **The order freezes.**
2. Waiter collects (cash, card machine, transfer) and records it on the tablet: **Pending cashier**.
3. A cashier checks the slip, alert or cash and presses **Confirm**: the order is settled and a receipt prints.
4. If it does not match, the cashier presses **Reject**: the bill stays unpaid and a supervisor is alerted.
5. If nobody confirms within 30 minutes the collection expires and the supervisors are alerted. Expired cash stays the waiter's responsibility.

Pay-link and per-bill transfer payments are confirmed by the payment provider; no person confirms them.

### The rules

| Situation | Rule |
| --- | --- |
| Printed bill | Bills say **BILL - NOT A RECEIPT**. Receipts are numbered like RCP-20261008-000123. A reprint is marked DUPLICATE |
| Changing a printed bill | Cancel the bill first. Waiters, bartenders and cashiers need a supervisor. It is refused while money is pending |
| Ways to pay | Cash (change given), card or POS terminal, bank transfer, pay link. Splits are allowed. The same card or transfer reference cannot be used twice. A part payment keeps the order open |
| Cashier's drawer | Open a cash session with a float. Cash needs an open session. Count blind at close. Only a supervisor or manager closes someone else's session |
| Waiter cash | Off by default. Allowed per facility or per person, with a limit. Over the limit, hand over first |
| Handing over cash | Waiter declares, cashier counts in front of them. A difference above N500 needs a supervisor's sign-off. You cannot receive your own handover or sign off one you received |
| Void | Unsent draft: no approval. Sent order: supervisor |
| Discount, comp, price change | Supervisor approval, unless within the facility's limit (discounts only) |
| Refund | Gives money back, does not reopen the order. Needs approval |
| Reversal | Cancels a payment and reopens the order. Only within 24 hours. Needs approval |
| Money records | Never edited or deleted. A correction is always a new entry |
| Card or transfer offline | Cannot be taken offline. Only cash, and only where the facility allows it |

### Staff sign-in limits

Five wrong PIN or password tries lock an account for 15 minutes. A card never works without its PIN.

## 18. When things go wrong

First read the banner on your screen, then find it below. Do not repeat an action until you know whether it went through: repeating it is how double charges and duplicate orders happen.

### Three different kinds of "offline"

| What failed | What still works | What does not |
| --- | --- | --- |
| **The internet** | Everything on the property: POS, tablets, kitchen screens, tickets, sign-in, stock | Online booking and the website portal, the admin portal from outside, card and mobile-money that need the internet |
| **A device cannot reach the server** (Wi-Fi or the server) | A small, safe set of actions, saved on the device (below) | Anything that needs a live answer |
| **The server itself is off** | Nothing can sign in on devices that use it | IT switches devices to the online server (chapter 14) |

### What each device saves while offline

| Device | Saved and sent later | Never saved |
| --- | --- | --- |
| Waiter tablet | New orders, cash and card-machine records. They show **PENDING CONFIRMATION** and **Saved on this tablet - pending sync**. Limit 50 actions or 30 minutes; beyond that: **Too many unsent actions. Reconnect to continue.** | Pay link, transfer, printing a bill, handing over cash, void, discount, approvals, sign-in, scans, equipment release and return |
| Cashier POS | Create order, add line, send order, open tab, and **cash-only** payments if the facility allows. Limit 200 entries or 30 minutes | Card, POS terminal, transfer, pay link, refunds, voids, discounts, approvals, cash-session actions, bookings, ticket scans |
| Kitchen screen | Nothing. Shows the last known board, read-only | Every change |
| Sports Entrance and Store | Nothing | Everything: a scan needs a live answer |

A cash payment taken offline on the POS says **CASH SAVED ON THIS TERMINAL - NOT CONFIRMED**. Give the customer a handwritten acknowledgement, not a receipt. The **Queue** screen on the POS (**Waiting to be confirmed** and **Refused by the server (needs a person)**) shows what is waiting. It sends them in order when the connection returns. Press **Send now** to try again.

### Messages and what to do

| You see | Meaning and action |
| --- | --- |
| **Cannot reach the server** / **Reconnecting to the server... showing last known data** | Check Wi-Fi. Keep working only on what the device allows offline. Tell IT if it lasts more than a few minutes |
| **You do not have permission to do that** | Your role cannot do it. Ask a supervisor |
| **A supervisor must authorise this** | Tick **A supervisor is here** and let them sign in, or send the request to the supervisors' devices |
| **Waiting for a supervisor to approve** | The order is locked until they decide. Find a supervisor |
| **No supervisor responded in time** | The request is still pending. Check **Approvals** later |
| **The bill is printed, so the order is frozen** | A supervisor must reopen the bill to change it |
| **Money was collected on this bill. Confirm or reject the collection before reopening the bill** | Do that in **Collected by waiters** first |
| **Print the bill first** | A waiter can only collect after the bill is printed |
| **Already collected by the waiter** | Confirm or reject it in **Collected by waiters**. Do not take payment again |
| **That is more than is left to collect on this bill** | Part is already collected. Take only the remaining amount |
| **Open a cash session before taking cash** | Open **Cash session** first |
| **Hand over your cash first** | You reached your cash limit. Hand over to the cashier |
| **This changed on another device. It has been refreshed** | Someone else changed it. Look and try again |
| **You cannot receive your own cash handover** / **The person who received the cash cannot sign off its variance** | Ask another cashier or supervisor |
| **That slot was just taken. Pick another** / **The hold expired** | Pick or hold again (chapter 7) |
| **Account locked after too many attempts** | Wait 15 minutes or see a supervisor |
| **Those credentials were not recognised** | Re-type the staff number and PIN carefully. After five wrong tries the account locks for 15 minutes |
| **This tablet has been revoked. Contact IT** / **This terminal is not registered or was revoked** | IT must register the device again (chapter 14) |
| **Tablet role not resolved** | Press **Reset tablet enrolment** and ask IT for a new code |
| **Enrolment failed (422)** | The registration code was used or has expired. Ask IT for a new one |
| **Something went wrong. Please try again** | Try once more. If it repeats, tell a supervisor and note what you pressed |

### Who to call

- **Customer and money questions:** your supervisor.
- **Devices, sign-in, codes, network:** IT administrator.
- **Prices, rules, staff:** the manager.

## 19. Glossary and quick reference

| Word | Meaning |
| --- | --- |
| **Approve / execute** | To *execute* is to do or request an action. To *approve* is to allow it. Supervisors, managers and the owner approve; nobody approves their own request |
| **Bill** | The pre-payment slip, marked NOT A RECEIPT. Printing it freezes the order |
| **Cash handover** | A waiter giving the cash they hold to a cashier, counted in front of both |
| **Cash session** | A cashier's shift at the drawer: opening float, payments, count at close |
| **Check out (a tablet)** | Recording which facility and person a tablet is used by for this shift |
| **Collected by waiter** | Money a waiter took at a table. It is not paid until a cashier confirms it |
| **Comp** | An item given free (complimentary) |
| **Device** | A tablet, POS, kitchen screen or scanner registered with the system |
| **Entitlement / QR ticket** | The ticket or pass a customer scans to enter or collect equipment |
| **Facility** | A place that sells or serves: restaurant, spa, pool, reception and so on |
| **Float** | The cash already in a drawer when a shift starts |
| **Hold** | A slot kept for a customer for a short time while they pay |
| **KDS** | The kitchen or bar display screen |
| **Local / property server** | The server inside the building |
| **Online server** | The server on the internet that runs the website and admin portal |
| **Order status** | Draft, Sent, In preparation, Ready, Served, Settled (paid), Voided |
| **Pending confirmation** | Saved on a device, not yet accepted by the server |
| **Reversal** | Cancelling a payment and reopening the order (within 24 hours) |
| **Settle** | Pay and close an order or a tab |
| **Step-up** | A supervisor's one-time sign-in on someone else's device to approve one action |
| **Tab** | A customer account that collects orders to be paid once |
| **Tender** | One method of payment in a payment: cash, card, transfer, pay link |
| **Variance** | The difference between what was expected or declared and what was counted |
| **Void** | Cancel an unpaid order |

### Quick reference: who approves what

| Action | Who approves |
| --- | --- |
| Void a sent order, comp, price change | Supervisor, manager, owner |
| Discount above the limit | Supervisor, manager, owner |
| Refund or reversal | Supervisor, manager, owner, accountant |
| Cancel or reopen a bill | Supervisor, manager, owner |
| Cash variance above N500 | Supervisor, manager |
| Stock adjustment | Supervisor, manager, owner |

### About this manual

This is a first full edition, written from the system as built on 8 October 2026. Items marked **Check with IT** need confirming on the live system. The product is being renamed from 007 Resort & Spa to SERI Resort; some screens, receipts and the website may still show the old name until that change is released.
