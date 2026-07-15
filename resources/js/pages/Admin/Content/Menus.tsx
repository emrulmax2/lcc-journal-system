import { useState } from 'react'
import { Head, router, useForm } from '@inertiajs/react'
import {
  AlertTriangle,
  ArrowDown,
  ArrowUp,
  EyeOff,
  ExternalLink,
  Info,
  Link2,
  Pencil,
  Plus,
  Trash2,
  X,
} from 'lucide-react'
import { ContentShell } from '@/components/admin/ContentShell'
import { Field, INPUT, Panel, SELECT, Spinner } from '@/components/admin/Shell'
import type {
  ContentPage,
  Destination,
  MenuItemRow,
  MenuRow,
  PageOption,
  RouteOption,
} from '@/lib/content'

/**
 * Navigation, as data.
 *
 * The navbar and footer used to hold seventeen footer links and a mega-menu as literal arrays
 * in TSX. Nine of them pointed at "#" or at the homepage, and three sent an author who wanted
 * the fee policy to the submission wizard. Links rot; hardcoded links rot INVISIBLY, because
 * nothing can check them.
 *
 * SO EVERY ITEM SHOWS THE URL IT ACTUALLY RESOLVES TO, resolved on the server by the same
 * MenuItem::url() the public navbar calls. If it says /article-processing-charges, that is
 * where the reader goes. There is no "#" this form can produce.
 *
 * THE DESTINATION IS A RADIO, and that is not a styling choice. A menu item points at EXACTLY
 * ONE of a page, a route, or an external URL — MenuItem::saving() throws otherwise, because an
 * item with two destinations resolves to whichever the accessor checks first and the editor
 * sees a link they did not choose. A radio makes the invalid state unrepresentable in the form;
 * the controller validates it again; the model throws as a last resort. Three layers, because
 * the failure is silent.
 */

type Props = ContentPage<{
  menus: MenuRow[]
  pages: PageOption[]
  routes: RouteOption[]
}>

const LOCATIONS: Record<string, string> = {
  navbar: 'Navbar',
  'navbar-mega': 'Navbar — mega-menu',
  footer: 'Footer',
  legal: 'Legal strip',
}

export default function ContentMenus({ menus, pages, routes, meta }: Props) {
  const [addingTo, setAddingTo] = useState<number | null>(null)
  const [editing, setEditing] = useState<number | null>(null)

  return (
    <>
      <Head>
        <title>{meta.title}</title>
      </Head>

      <ContentShell
        title="Navigation"
        description="The navbar, the authors mega-menu, the footer columns and the legal strip. Every item resolves to a real URL."
      >
        <div className="space-y-8">
          {menus.map((menu) => {
            const roots = menu.items.filter((item) => item.parentId === null)

            return (
              <Panel
                key={menu.id}
                title={menu.name}
                description={`${LOCATIONS[menu.location] ?? menu.location} · ${menu.items.length} ${
                  menu.items.length === 1 ? 'item' : 'items'
                }`}
                actions={
                  <button
                    type="button"
                    onClick={() => {
                      setAddingTo(addingTo === menu.id ? null : menu.id)
                      setEditing(null)
                    }}
                    className="btn-secondary px-4 py-2 text-xs"
                  >
                    <Plus className="h-4 w-4" aria-hidden="true" />
                    Add item
                  </button>
                }
              >
                {addingTo === menu.id && (
                  <div className="mb-6">
                    <ItemForm
                      menu={menu}
                      pages={pages}
                      routes={routes}
                      onDone={() => setAddingTo(null)}
                      onCancel={() => setAddingTo(null)}
                    />
                  </div>
                )}

                {menu.items.length === 0 ? (
                  <p className="text-sm text-ink-600">
                    No items. This menu renders nothing at all — which is honest, and better than
                    a link that goes nowhere.
                  </p>
                ) : (
                  <ul className="divide-y divide-ink-200">
                    {roots.map((item, index) => {
                      const children = menu.items.filter((child) => child.parentId === item.id)

                      return (
                        <li key={item.id} className="py-3">
                          <Row
                            menu={menu}
                            item={item}
                            siblings={roots}
                            index={index}
                            pages={pages}
                            routes={routes}
                            editing={editing === item.id}
                            onEdit={() => {
                              setEditing(editing === item.id ? null : item.id)
                              setAddingTo(null)
                            }}
                            onDone={() => setEditing(null)}
                          />

                          {children.length > 0 && (
                            <ul className="mt-3 space-y-3 border-l-2 border-ink-200 pl-5">
                              {children.map((child, childIndex) => (
                                <li key={child.id}>
                                  <Row
                                    menu={menu}
                                    item={child}
                                    siblings={children}
                                    index={childIndex}
                                    pages={pages}
                                    routes={routes}
                                    editing={editing === child.id}
                                    onEdit={() => {
                                      setEditing(editing === child.id ? null : child.id)
                                      setAddingTo(null)
                                    }}
                                    onDone={() => setEditing(null)}
                                  />
                                </li>
                              ))}
                            </ul>
                          )}
                        </li>
                      )
                    })}
                  </ul>
                )}
              </Panel>
            )
          })}
        </div>
      </ContentShell>
    </>
  )
}

/* ----------------------------------- Row ----------------------------------- */

function Row({
  menu,
  item,
  siblings,
  index,
  pages,
  routes,
  editing,
  onEdit,
  onDone,
}: {
  menu: MenuRow
  item: MenuItemRow
  siblings: MenuItemRow[]
  index: number
  pages: PageOption[]
  routes: RouteOption[]
  editing: boolean
  onEdit: () => void
  onDone: () => void
}) {
  const [deleting, setDeleting] = useState(false)

  const move = (direction: -1 | 1) => {
    const next = [...siblings]
    const target = index + direction

    if (target < 0 || target >= next.length) return
    ;[next[index], next[target]] = [next[target], next[index]]

    router.post(
      `/admin/content/menus/${menu.id}/reorder`,
      { order: next.map((sibling) => sibling.id) },
      { preserveScroll: true },
    )
  }

  if (editing) {
    return (
      <ItemForm
        menu={menu}
        item={item}
        pages={pages}
        routes={routes}
        onDone={onDone}
        onCancel={onDone}
      />
    )
  }

  return (
    <div className="flex flex-wrap items-start gap-4">
      <div className="flex flex-col gap-1 pt-0.5">
        <button
          type="button"
          onClick={() => move(-1)}
          disabled={index === 0}
          aria-label={`Move “${item.label}” up`}
          className="inline-flex h-7 w-7 cursor-pointer items-center justify-center rounded border border-ink-300
                     text-ink-600 transition-colors duration-200 hover:border-ink-900 hover:text-ink-900
                     disabled:cursor-not-allowed disabled:opacity-40"
        >
          <ArrowUp className="h-3.5 w-3.5" aria-hidden="true" />
        </button>
        <button
          type="button"
          onClick={() => move(1)}
          disabled={index === siblings.length - 1}
          aria-label={`Move “${item.label}” down`}
          className="inline-flex h-7 w-7 cursor-pointer items-center justify-center rounded border border-ink-300
                     text-ink-600 transition-colors duration-200 hover:border-ink-900 hover:text-ink-900
                     disabled:cursor-not-allowed disabled:opacity-40"
        >
          <ArrowDown className="h-3.5 w-3.5" aria-hidden="true" />
        </button>
      </div>

      <div className="min-w-0 flex-1">
        <div className="flex flex-wrap items-center gap-2">
          <span className="font-medium text-ink-900">{item.label}</span>

          <DestinationBadge destination={item.destination} />

          {!item.isActive && (
            <span className="inline-flex items-center gap-1.5 rounded-full bg-ink-100 px-2 py-0.5 text-[11px] font-semibold text-ink-700">
              <EyeOff className="h-3 w-3" aria-hidden="true" />
              Hidden
            </span>
          )}

          {item.opensInNewTab && (
            <span className="inline-flex items-center gap-1.5 rounded-full bg-ink-100 px-2 py-0.5 text-[11px] font-semibold text-ink-700">
              <ExternalLink className="h-3 w-3" aria-hidden="true" />
              New tab
            </span>
          )}
        </div>

        {/* WHERE IT ACTUALLY GOES. The entire reason this table exists. */}
        <p className="mt-1 flex items-center gap-1.5 font-mono text-xs text-ink-600">
          <Link2 className="h-3.5 w-3.5 shrink-0 text-ink-500" aria-hidden="true" />
          {item.url}
        </p>

        {item.description && <p className="mt-1 text-sm text-ink-600">{item.description}</p>}

        {item.pointsAtDraft && (
          <p className="mt-2 flex items-start gap-2 rounded-md bg-danger-50 p-2.5 text-xs text-danger-800">
            <AlertTriangle className="mt-0.5 h-3.5 w-3.5 shrink-0" aria-hidden="true" />
            <span>
              This points at a page that is not published. Every reader who clicks it gets a 404.
              Publish the page, or hide this item.
            </span>
          </p>
        )}
      </div>

      <div className="flex items-center gap-2">
        {deleting ? (
          <>
            <button
              type="button"
              onClick={() =>
                router.delete(`/admin/content/menu-items/${item.id}`, { preserveScroll: true })
              }
              className="btn inline-flex bg-danger-700 px-3 py-1.5 text-xs text-white hover:bg-danger-800"
            >
              <Trash2 className="h-3.5 w-3.5" aria-hidden="true" />
              Delete
            </button>
            <button
              type="button"
              onClick={() => setDeleting(false)}
              className="btn-ghost px-3 py-1.5 text-xs"
            >
              Cancel
            </button>
          </>
        ) : (
          <>
            <button type="button" onClick={onEdit} className="btn-ghost px-3 py-1.5 text-xs">
              <Pencil className="h-3.5 w-3.5" aria-hidden="true" />
              Edit
            </button>
            <button
              type="button"
              onClick={() => setDeleting(true)}
              aria-label={`Remove “${item.label}”`}
              className="inline-flex h-9 w-9 cursor-pointer items-center justify-center rounded-lg border
                         border-ink-300 text-danger-700 transition-colors duration-200
                         hover:border-danger-600 hover:bg-danger-50"
            >
              <Trash2 className="h-4 w-4" aria-hidden="true" />
            </button>
          </>
        )}
      </div>
    </div>
  )
}

function DestinationBadge({ destination }: { destination: Destination }) {
  const look: Record<Destination, string> = {
    page: 'bg-brand-50 text-brand-800',
    route: 'bg-ink-100 text-ink-700',
    external: 'bg-gold-50 text-gold-700',
  }

  const label: Record<Destination, string> = {
    page: 'Page',
    route: 'Route',
    external: 'External',
  }

  return (
    <span
      className={`inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[11px] font-semibold ${look[destination]}`}
    >
      <Link2 className="h-3 w-3" aria-hidden="true" />
      {label[destination]}
    </span>
  )
}

/* --------------------------------- Item form -------------------------------- */

function ItemForm({
  menu,
  item,
  pages,
  routes,
  onDone,
  onCancel,
}: {
  menu: MenuRow
  item?: MenuItemRow
  pages: PageOption[]
  routes: RouteOption[]
  onDone: () => void
  onCancel: () => void
}) {
  const [destination, setDestination] = useState<Destination>(item?.destination ?? 'page')

  const form = useForm({
    label: item?.label ?? '',
    description: item?.description ?? '',
    page_id: item?.pageId ?? null,
    route_name: item?.routeName ?? null,
    external_url: item?.externalUrl ?? '',
    parent_id: item?.parentId ?? null,
    opens_in_new_tab: item?.opensInNewTab ?? false,
    is_active: item?.isActive ?? true,
  })

  const { data, setData, errors, processing } = form

  /**
   * ONE destination, and the other two are NULLED as you switch.
   *
   * Not merely hidden — cleared. A hidden field that still carries a page_id is exactly the
   * "points at two places" state MenuItem::saving() throws on, and the editor would have no
   * idea why their save was refused.
   */
  const choose = (next: Destination) => {
    setDestination(next)

    setData((current) => ({
      ...current,
      page_id: next === 'page' ? current.page_id : null,
      route_name: next === 'route' ? current.route_name : null,
      external_url: next === 'external' ? current.external_url : '',
    }))
  }

  const submit = (e: React.FormEvent) => {
    e.preventDefault()

    const options = { preserveScroll: true, onSuccess: onDone }

    if (item) {
      form.put(`/admin/content/menu-items/${item.id}`, options)
    } else {
      form.post(`/admin/content/menus/${menu.id}/items`, options)
    }
  }

  // The server refuses an item that points nowhere or at two places at once, under this key.
  const destinationError = (errors as Record<string, string | undefined>).destination

  const parents = menu.items.filter((candidate) => candidate.parentId === null && candidate.id !== item?.id)

  return (
    <form
      onSubmit={submit}
      aria-label={item ? `Edit ${item.label}` : 'New menu item'}
      className="rounded-lg border border-brand-300 bg-brand-50/40 p-4"
    >
      <div className="grid gap-5 sm:grid-cols-2">
        <Field label="Label" htmlFor={`label-${item?.id ?? 'new'}`} error={errors.label}>
          <input
            id={`label-${item?.id ?? 'new'}`}
            type="text"
            value={data.label}
            onChange={(e) => setData('label', e.target.value)}
            className={INPUT}
            required
          />
        </Field>

        <Field
          label="Description"
          htmlFor={`description-${item?.id ?? 'new'}`}
          error={errors.description}
          hint="The mega-menu shows this under the label. The footer ignores it."
        >
          <input
            id={`description-${item?.id ?? 'new'}`}
            type="text"
            value={data.description}
            onChange={(e) => setData('description', e.target.value)}
            className={INPUT}
          />
        </Field>
      </div>

      {/* ------------------------------- Destination ------------------------------ */}
      <fieldset className="mt-5">
        <legend className="text-sm font-medium text-ink-800">Where it goes</legend>

        <p className="mt-1 flex items-start gap-1.5 text-sm text-ink-600">
          <Info className="mt-0.5 h-3.5 w-3.5 shrink-0 text-ink-500" aria-hidden="true" />
          <span>
            Exactly one. An item that points at two places resolves to whichever is checked
            first — and that is not a choice anybody made.
          </span>
        </p>

        <div className="mt-3 flex flex-wrap gap-4">
          {(['page', 'route', 'external'] as Destination[]).map((option) => (
            <label
              key={option}
              className="flex cursor-pointer items-center gap-2 text-sm text-ink-800"
            >
              <input
                type="radio"
                name={`destination-${item?.id ?? 'new'}`}
                value={option}
                checked={destination === option}
                onChange={() => choose(option)}
                className="h-4 w-4 cursor-pointer accent-brand-700"
              />
              {option === 'page' ? 'A page' : option === 'route' ? 'A section of the site' : 'An external URL'}
            </label>
          ))}
        </div>

        <div className="mt-4">
          {destination === 'page' && (
            <Field
              label="Page"
              htmlFor={`page-${item?.id ?? 'new'}`}
              error={errors.page_id}
              hint="Only published pages. Linking a draft is linking a 404."
            >
              <select
                id={`page-${item?.id ?? 'new'}`}
                value={data.page_id ?? ''}
                onChange={(e) => setData('page_id', e.target.value ? Number(e.target.value) : null)}
                className={SELECT}
                required
              >
                <option value="">Choose a page…</option>
                {pages.map((page) => (
                  <option key={page.id} value={page.id}>
                    {page.title} — {page.url}
                  </option>
                ))}
              </select>
            </Field>
          )}

          {destination === 'route' && (
            <Field
              label="Section"
              htmlFor={`route-${item?.id ?? 'new'}`}
              error={errors.route_name}
              hint="An allow-list built from the router itself, so a renamed route cannot silently 404."
            >
              <select
                id={`route-${item?.id ?? 'new'}`}
                value={data.route_name ?? ''}
                onChange={(e) => setData('route_name', e.target.value || null)}
                className={SELECT}
                required
              >
                <option value="">Choose a section…</option>
                {routes.map((route) => (
                  <option key={route.name} value={route.name}>
                    {route.path} ({route.name})
                  </option>
                ))}
              </select>
            </Field>
          )}

          {destination === 'external' && (
            <Field
              label="External URL"
              htmlFor={`external-${item?.id ?? 'new'}`}
              error={errors.external_url}
            >
              <input
                id={`external-${item?.id ?? 'new'}`}
                type="url"
                value={data.external_url}
                onChange={(e) => setData('external_url', e.target.value)}
                className={INPUT}
                placeholder="https://"
                required
              />
            </Field>
          )}
        </div>

        {destinationError && (
          <p className="mt-2 flex items-start gap-1.5 text-sm text-danger-700">
            <AlertTriangle className="mt-0.5 h-3.5 w-3.5 shrink-0" aria-hidden="true" />
            {destinationError}
          </p>
        )}
      </fieldset>

      <div className="mt-5 grid gap-5 sm:grid-cols-2">
        <Field
          label="Nested under"
          htmlFor={`parent-${item?.id ?? 'new'}`}
          error={errors.parent_id}
          hint="Only the mega-menu renders children."
        >
          <select
            id={`parent-${item?.id ?? 'new'}`}
            value={data.parent_id ?? ''}
            onChange={(e) => setData('parent_id', e.target.value ? Number(e.target.value) : null)}
            className={SELECT}
          >
            <option value="">Top level</option>
            {parents.map((parent) => (
              <option key={parent.id} value={parent.id}>
                {parent.label}
              </option>
            ))}
          </select>
        </Field>

        <div className="flex flex-wrap items-center gap-5 pt-7">
          <label className="flex cursor-pointer items-center gap-2 text-sm text-ink-800">
            <input
              type="checkbox"
              checked={data.is_active}
              onChange={(e) => setData('is_active', e.target.checked)}
              className="h-4 w-4 cursor-pointer accent-brand-700"
            />
            Visible
          </label>

          <label className="flex cursor-pointer items-center gap-2 text-sm text-ink-800">
            <input
              type="checkbox"
              checked={data.opens_in_new_tab}
              onChange={(e) => setData('opens_in_new_tab', e.target.checked)}
              className="h-4 w-4 cursor-pointer accent-brand-700"
            />
            Opens in a new tab
          </label>
        </div>
      </div>

      <div className="mt-5 flex gap-2">
        <button type="submit" disabled={processing} className="btn-primary">
          {processing ? <Spinner /> : null}
          {item ? 'Save item' : 'Add item'}
        </button>
        <button type="button" onClick={onCancel} className="btn-ghost">
          <X className="h-4 w-4" aria-hidden="true" />
          Cancel
        </button>
      </div>
    </form>
  )
}
