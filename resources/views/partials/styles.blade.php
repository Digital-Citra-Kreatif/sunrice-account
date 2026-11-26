{{--
    Plain styles for the account-* classes, pushed to the layout's head (the
    package's minimal layout and a site's own alike). A theme restyles them
    by setting the --account-* colours, or its own rules for the classes.
--}}
<style nonce="{{ cms_nonce() }}">
    /* The colours, for any layout: the package's own and a theme's (which sets its own over them, on :root or below).
       Dark colours only in the package's layout: a theme decides its own scheme. */
    :where(:root) { --account-fg: #18181b; --account-muted: #71717a; --account-bg: #fafafa; --account-card: #fff; --account-line: #e4e4e7; --account-field-line: #8a8a93; --account-accent: #18181b; --account-on-accent: #fff; --account-danger: #b91c1c; --account-success: #15803d; }
    .account-site { color-scheme: light dark; }
    @media (prefers-color-scheme: dark) { .account-site { --account-fg: #f4f4f5; --account-muted: #a1a1aa; --account-bg: #09090b; --account-card: #18181b; --account-line: #27272a; --account-field-line: #71717a; --account-accent: #f4f4f5; --account-on-accent: #18181b; --account-danger: #f87171; --account-success: #4ade80; } }
    .account-body { margin: 0; background: var(--account-bg); color: var(--account-fg); font: 16px/1.55 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
    .account-main { max-width: 64rem; margin: 0 auto; padding: 2.5rem 1rem 4rem; box-sizing: border-box; }
    .account-main *, .account-main *::before, .account-main *::after { box-sizing: border-box; }
    .account-main h1 { font-size: 1.75rem; line-height: 1.2; margin: 0 0 1.25rem; letter-spacing: -0.01em; }
    .account-main h2 { font-size: 1.15rem; margin: 2rem 0 .75rem; }
    .account-main a:not(.account-button) { color: inherit; text-underline-offset: 3px; }
    .account-actions { display: flex; flex-wrap: wrap; gap: .75rem; align-items: center; }
    .account-auth { max-width: 26rem; margin: 2rem auto; padding: 2rem; background: var(--account-card); border: 1px solid var(--account-line); border-radius: 12px; }
    .account-form { display: flex; flex-direction: column; gap: 1rem; margin: 0 0 1rem; }
    .account-field { display: flex; flex-direction: column; gap: .35rem; font-size: .9rem; font-weight: 500; }
    .account-field input, .account-field select, .account-field textarea { font: inherit; font-weight: 400; color: inherit; background: var(--account-card); border: 1px solid var(--account-field-line); border-radius: 8px; padding: .6rem .75rem; width: 100%; }
    .account-field input:focus, .account-field select:focus, .account-field textarea:focus { outline: 2px solid var(--account-accent); outline-offset: 1px; }
    .account-field [aria-invalid="true"] { border-color: var(--account-danger); }
    .account-main a:focus-visible, .account-main button:focus-visible, .account-check input:focus-visible { outline: 2px solid var(--account-accent); outline-offset: 2px; }
    .account-field__help { color: var(--account-muted); font-weight: 400; font-size: .85rem; }
    .account-field__error { color: var(--account-danger); font-weight: 400; font-size: .85rem; }
    .account-check { display: flex; gap: .5rem; align-items: center; font-size: .9rem; }
    .account-button { display: inline-flex; justify-content: center; align-items: center; gap: .5rem; font: inherit; font-weight: 600; font-size: .95rem; padding: .65rem 1rem; border-radius: 8px; border: 1px solid var(--account-line); background: var(--account-card); color: inherit; cursor: pointer; text-decoration: none; }
    .account-button--primary { background: var(--account-accent); border-color: var(--account-accent); color: var(--account-on-accent); }
    .account-button--danger { color: var(--account-danger); border-color: currentColor; }
    .account-link { font: inherit; background: none; border: 0; padding: 0; color: inherit; text-decoration: underline; text-underline-offset: 3px; cursor: pointer; }
    .account-auth__link { font-size: .9rem; color: var(--account-muted); }
    .account-auth__hint { color: var(--account-muted); font-size: .9rem; margin: 0 0 .75rem; }
    .account-auth__or { text-align: center; color: var(--account-muted); font-size: .85rem; margin: 1.25rem 0; }
    .account-auth__alt { margin: 1.25rem 0 0; font-size: .9rem; color: var(--account-muted); }
    .account-message { padding: .75rem 1rem; border-radius: 8px; border: 1px solid var(--account-line); margin: 0 0 1rem; font-size: .9rem; }
    .account-message--success { border-color: var(--account-success); color: var(--account-success); }
    .account-message--error { border-color: var(--account-danger); color: var(--account-danger); }
    .account { display: grid; grid-template-columns: 13rem 1fr; gap: 2.5rem; align-items: start; }
    .account-nav { position: sticky; top: 1.5rem; display: flex; flex-direction: column; gap: 1rem; }
    .account-nav__list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: .15rem; }
    .account-nav__link { display: block; padding: .5rem .75rem; border-radius: 8px; text-decoration: none; }
    .account-nav__link:hover { background: var(--account-line); }
    .account-nav__link.is-active { background: var(--account-accent); color: var(--account-on-accent); }
    .account-nav__signout { padding: 0 .75rem; font-size: .9rem; color: var(--account-muted); }
    .account__page { min-width: 0; }
    .account__page > form, .account-cards > *, .account-card { background: var(--account-card); border: 1px solid var(--account-line); border-radius: 12px; padding: 1.5rem; }
    .account-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(16rem, 1fr)); gap: 1rem; margin-top: 1.5rem; }
    .account-announcement { background: var(--account-card); border: 1px solid var(--account-line); border-left: 4px solid var(--account-accent); border-radius: 8px; padding: 1rem 1.25rem; margin: 0 0 1.5rem; }
    .account-main .account-announcement__title { margin: 0 0 .35rem; font-size: 1rem; }
    .account__page > form > p:first-child { margin-top: 0; }
    .account-announcement__message { margin: 0; }
    .break-all { word-break: break-all; }
    @media (max-width: 720px) { .account { grid-template-columns: 1fr; gap: 1.5rem; } .account-nav { position: static; } .account-nav__list { flex-direction: row; flex-wrap: wrap; } .account-auth { padding: 1.25rem; margin: 0 auto; } }
</style>
