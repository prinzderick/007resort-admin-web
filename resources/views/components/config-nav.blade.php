<x-subnav :items="[
    ['Overview', 'config.index', 'config.index', []],
    ['Facilities', 'config.facilities', 'config.facilities', ['facility.configure', 'config.manage']],
    ['Catalog & prices', 'config.catalog', 'config.catalog', ['pricing.manage', 'config.manage', 'catalog.availability.manage']],
    ['Tax / VAT', 'config.tax', 'config.tax', ['config.manage']],
    ['Memberships', 'config.memberships', 'config.memberships', ['membership.plan.manage', 'config.manage']],
    ['Booking rules', 'config.bookings', 'config.bookings', ['facility.configure', 'config.manage']],
    ['Ticket types', 'config.tickets', 'config.tickets', ['facility.configure', 'config.manage']],
    ['KDS routing', 'config.kds', 'config.kds', ['facility.configure', 'config.manage']],
    ['Payment timing', 'config.payments', 'config.payments', ['facility.configure', 'config.manage']],
]" />
