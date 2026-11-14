# Flexihub UI Design System

## Visual direction

Flexihub uses a consistent dark navy and blue interface. Avoid introducing orange as a primary brand colour or creating a separate visual language for individual modules.

## Shared application pages

Authenticated pages must use the shared shell:

- `includes/header.php` for the top bar, navigation and opening page container.
- `includes/sidebar.php` for navigation.
- `includes/footer.php` for the shared fixed footer and closing layout.
- `assets/css/flexihub-ui-polish.css` as the common component layer, loaded after the base and dark styles.

Do not add another page-specific header, sidebar, footer, or competing global stylesheet.

## Design tokens

Use the CSS custom properties already defined in the shared stylesheet:

- Background: `--fh-bg`
- Panels: `--fh-panel`, `--fh-panel-raised`
- Borders: `--fh-line`
- Text: `--fh-text`, `--fh-muted`
- Primary action: `--fh-primary`, `--fh-primary-bright`
- Status: `--fh-success`, `--fh-warning`, `--fh-danger`
- Shape and depth: `--fh-radius`, `--fh-shadow`, `--fh-focus`

Tenant branding may customise approved brand accents, but must preserve readable contrast and the platform's shared layout.

## Component rules

1. Use one page heading pattern with a clear title, short description, and right-aligned actions.
2. Use shared cards/panels for content sections; avoid arbitrary inline colours, shadows and radii.
3. Use the shared button hierarchy: primary for the main action, secondary for navigation/cancel, and semantic success/warning/danger only when appropriate.
4. Forms use consistent labels, control heights, spacing, validation messages and grouped sections.
5. Tables use responsive wrappers, consistent headers, row spacing, empty states and action placement.
6. Status values use consistent badges and must communicate actual state, not decorative colour.
7. Destructive actions require clear labels and confirmation where data loss or service interruption is possible.
8. All controls need visible focus, disabled and error states.
9. Layouts must remain usable at desktop, tablet and mobile widths; tables may scroll horizontally rather than crush columns.
10. Respect reduced-motion preferences.

## Standalone pages

Login, public hotspot/captive portal and other unauthenticated pages may use a standalone layout, but must use the same navy/blue palette, typography, control treatment, card styling and responsive rules. They should not load the authenticated sidebar or fixed footer unless their workflow requires them.

## Implementation rules

- Prefer semantic shared classes over repeated inline styles.
- Add reusable components to the shared stylesheet rather than styling the same component differently in each module.
- Keep module-specific CSS limited to genuinely unique visualisations or workflows.
- Do not alter business logic merely to achieve a visual change.
- Verify the common shell and representative list, form, detail, settings, and standalone pages after stylesheet changes.
