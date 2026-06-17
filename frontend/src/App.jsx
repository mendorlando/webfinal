import { startTransition, useDeferredValue, useEffect, useState } from 'react';

const API_URL = import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000/api';

const emptyNote = {
  title: '',
  content: '',
  tags: '',
  isPinned: false
};

const emptyCheckout = {
  methodId: 'stripe',
  plan: 'pro',
  customerName: '',
  customerEmail: ''
};

async function request(path, options = {}) {
  const response = await fetch(`${API_URL}${path}`, {
    headers: {
      'Content-Type': 'application/json',
      ...(options.headers ?? {})
    },
    ...options
  });

  if (response.status === 204) {
    return null;
  }

  const data = await response.json();
  if (!response.ok) {
    throw new Error(data.detail ?? data.error ?? 'Ocurrio un error inesperado.');
  }

  return data;
}

export default function App() {
  const [notes, setNotes] = useState([]);
  const [stats, setStats] = useState({ notesTotal: 0, pinnedTotal: 0, checkoutTotal: 0 });
  const [methods, setMethods] = useState([]);
  const [noteForm, setNoteForm] = useState(emptyNote);
  const [checkoutForm, setCheckoutForm] = useState(emptyCheckout);
  const [editingId, setEditingId] = useState(null);
  const [search, setSearch] = useState('');
  const [loading, setLoading] = useState(true);
  const [savingNote, setSavingNote] = useState(false);
  const [sendingCheckout, setSendingCheckout] = useState(false);
  const [error, setError] = useState('');
  const [successMessage, setSuccessMessage] = useState('');

  const deferredSearch = useDeferredValue(search);

  const query = deferredSearch.trim().toLowerCase();
  const filteredNotes = !query
    ? notes
    : notes.filter((note) => {
        const haystack = [note.title, note.content, note.tags.join(' ')].join(' ').toLowerCase();
        return haystack.includes(query);
      });

  useEffect(() => {
    async function bootstrap() {
      try {
        setLoading(true);
        setError('');

        const [notesResponse, methodsResponse] = await Promise.all([
          request('/notes'),
          request('/payments/methods')
        ]);

        setNotes(notesResponse.notes);
        setStats(notesResponse.stats);
        setMethods(methodsResponse.methods);

        if (methodsResponse.methods.length > 0) {
          setCheckoutForm((current) => ({
            ...current,
            methodId: methodsResponse.methods[0].id
          }));
        }
      } catch (err) {
        setError(err.message);
      } finally {
        setLoading(false);
      }
    }

    bootstrap();
  }, []);

  function refreshLocalStats(nextNotes, checkoutIncrement = 0) {
    setStats((current) => ({
      notesTotal: nextNotes.length,
      pinnedTotal: nextNotes.filter((note) => note.isPinned).length,
      checkoutTotal: current.checkoutTotal + checkoutIncrement
    }));
  }

  function handleNoteChange(event) {
    const { name, value, type, checked } = event.target;
    setNoteForm((current) => ({
      ...current,
      [name]: type === 'checkbox' ? checked : value
    }));
  }

  function handleCheckoutChange(event) {
    const { name, value } = event.target;
    setCheckoutForm((current) => ({
      ...current,
      [name]: value
    }));
  }

  function resetNoteForm() {
    setEditingId(null);
    setNoteForm(emptyNote);
  }

  async function handleSubmitNote(event) {
    event.preventDefault();

    try {
      setSavingNote(true);
      setError('');
      setSuccessMessage('');

      const payload = {
        ...noteForm,
        tags: noteForm.tags
      };

      if (editingId) {
        const response = await request(`/notes/${editingId}`, {
          method: 'PUT',
          body: JSON.stringify(payload)
        });

        const nextNotes = notes.map((note) => (note.id === editingId ? response.note : note));
        setNotes(nextNotes);
        refreshLocalStats(nextNotes);
        setSuccessMessage('Nota actualizada correctamente.');
      } else {
        const response = await request('/notes', {
          method: 'POST',
          body: JSON.stringify(payload)
        });

        const nextNotes = [response.note, ...notes].sort((a, b) => {
          if (a.isPinned !== b.isPinned) {
            return Number(b.isPinned) - Number(a.isPinned);
          }
          return new Date(b.updatedAt) - new Date(a.updatedAt);
        });

        setNotes(nextNotes);
        refreshLocalStats(nextNotes);
        setSuccessMessage('Nota creada correctamente.');
      }

      resetNoteForm();
    } catch (err) {
      setError(err.message);
    } finally {
      setSavingNote(false);
    }
  }

  function handleEdit(note) {
    startTransition(() => {
      setEditingId(note.id);
      setNoteForm({
        title: note.title,
        content: note.content,
        tags: note.tags.join(', '),
        isPinned: note.isPinned
      });
      setSuccessMessage('');
      setError('');
    });
  }

  async function handleDelete(noteId) {
    const confirmed = window.confirm('Esta nota se eliminara. Quieres continuar?');
    if (!confirmed) {
      return;
    }

    try {
      setError('');
      setSuccessMessage('');
      await request(`/notes/${noteId}`, { method: 'DELETE' });
      const nextNotes = notes.filter((note) => note.id !== noteId);
      setNotes(nextNotes);
      refreshLocalStats(nextNotes);

      if (editingId === noteId) {
        resetNoteForm();
      }

      setSuccessMessage('Nota eliminada.');
    } catch (err) {
      setError(err.message);
    }
  }

  async function handleCheckout(event) {
    event.preventDefault();

    try {
      setSendingCheckout(true);
      setError('');

      const response = await request('/payments/checkout', {
        method: 'POST',
        body: JSON.stringify(checkoutForm)
      });

      const paymentRedirectUrl = response.redirectUrl ?? response.checkout?.redirectUrl;

      if (paymentRedirectUrl) {
        window.location.href = paymentRedirectUrl;
        return;
      }

      setCheckoutForm((current) => ({
        ...current,
        customerName: '',
        customerEmail: ''
      }));

      setStats((current) => ({
        ...current,
        checkoutTotal: current.checkoutTotal + 1
      }));

      setSuccessMessage(
        `Pago registrado. Referencia #${response.checkout.id}. ${response.message || ''}`
      );
    } catch (err) {
      setError(err.message);
    } finally {
      setSendingCheckout(false);
    }
  }

  return (
    <main className="page-shell">
      <section className="hero">
        <div className="hero-copy">
          <p className="eyebrow">Proyecto escolar listo para crecer</p>
          <h1>NotasFlow</h1>
        </div>

        <div className="stats-grid">
          <article className="stat-card">
            <span>Total notas</span>
            <strong>{stats.notesTotal}</strong>
          </article>
          <article className="stat-card">
            <span>Notas fijadas</span>
            <strong>{stats.pinnedTotal}</strong>
          </article>
          <article className="stat-card">
            <span>Checkouts</span>
            <strong>{stats.checkoutTotal}</strong>
          </article>
        </div>
      </section>

      {(error || successMessage) && (
        <section className="feedback-row">
          {error ? <div className="feedback error">{error}</div> : null}
          {successMessage ? <div className="feedback success">{successMessage}</div> : null}
        </section>
      )}

      <section className="workspace">
        <div className="panel form-panel">
          <div className="section-heading">
            <h2>{editingId ? 'Editar nota' : 'Nueva nota'}</h2>
            <p>Crea apuntes, fijalos y organizalos con etiquetas.</p>
          </div>

          <form className="stack-form" onSubmit={handleSubmitNote}>
            <label>
              <span>Titulo</span>
              <input
                name="title"
                value={noteForm.title}
                onChange={handleNoteChange}
                placeholder="Ej. Resumen de algoritmos"
              />
            </label>

            <label>
              <span>Contenido</span>
              <textarea
                name="content"
                value={noteForm.content}
                onChange={handleNoteChange}
                rows="7"
                placeholder="Escribe aqui el cuerpo de la nota..."
              />
            </label>

            <label>
              <span>Etiquetas</span>
              <input
                name="tags"
                value={noteForm.tags}
                onChange={handleNoteChange}
                placeholder="escuela, examen, backend"
              />
            </label>

            <label className="checkbox-line">
              <input
                type="checkbox"
                name="isPinned"
                checked={noteForm.isPinned}
                onChange={handleNoteChange}
              />
              <span>Fijar como importante</span>
            </label>

            <div className="button-row">
              <button className="primary-button" type="submit" disabled={savingNote}>
                {savingNote ? 'Guardando...' : editingId ? 'Actualizar' : 'Crear nota'}
              </button>
              <button className="ghost-button" type="button" onClick={resetNoteForm}>
                Limpiar
              </button>
            </div>
          </form>
        </div>

        <div className="panel notes-panel">
          <div className="section-heading">
            <h2>Biblioteca de notas</h2>
            <p>Busca rapido entre tus apuntes y administra todo desde un solo lugar.</p>
          </div>

          <input
            className="search-input"
            value={search}
            onChange={(event) => setSearch(event.target.value)}
            placeholder="Buscar por titulo, contenido o etiqueta"
          />

          {loading ? (
            <div className="empty-state">Cargando notas...</div>
          ) : filteredNotes.length === 0 ? (
            <div className="empty-state">No hay notas que coincidan con tu busqueda.</div>
          ) : (
            <div className="notes-list">
              {filteredNotes.map((note) => (
                <article className="note-card" key={note.id}>
                  <div className="note-card-header">
                    <div>
                      <h3>{note.title}</h3>
                      <p>{new Date(note.updatedAt).toLocaleString('es-MX')}</p>
                    </div>
                    {note.isPinned ? <span className="pin-badge">Fijada</span> : null}
                  </div>

                  <p className="note-content">{note.content}</p>

                  <div className="tags-row">
                    {note.tags.map((tag) => (
                      <span className="tag-chip" key={`${note.id}-${tag}`}>
                        {tag}
                      </span>
                    ))}
                  </div>

                  <div className="button-row">
                    <button className="ghost-button" type="button" onClick={() => handleEdit(note)}>
                      Editar
                    </button>
                    <button
                      className="danger-button"
                      type="button"
                      onClick={() => handleDelete(note.id)}
                    >
                      Eliminar
                    </button>
                  </div>
                </article>
              ))}
            </div>
          )}
        </div>
      </section>

      <section className="payments-layout">
        <div className="panel methods-panel">
          <div className="section-heading">
            <h2>Metodos de pago</h2>
            <p>
              La app ya contempla Stripe, PayPal y Mercado Pago para la parte comercial.
            </p>
          </div>

          <div className="method-grid">
            {methods.map((method) => (
              <article className="method-card" key={method.id}>
                <h3>{method.name}</h3>
                <p>{method.description}</p>
                <a href={method.providerUrl} target="_blank" rel="noreferrer">
                  Ver proveedor
                </a>
              </article>
            ))}
          </div>
        </div>

        <div className="panel checkout-panel">
          <div className="section-heading">
            <h2>Checkout demo</h2>
            <p>
              Stripe intenta abrir un checkout real si el backend tiene credenciales
              activas. Si no, registra el intento en modo demo.
            </p>
          </div>

          <form className="stack-form" onSubmit={handleCheckout}>
            <label>
              <span>Metodo</span>
              <select
                name="methodId"
                value={checkoutForm.methodId}
                onChange={handleCheckoutChange}
              >
                {methods.map((method) => (
                  <option key={method.id} value={method.id}>
                    {method.name}
                  </option>
                ))}
              </select>
            </label>

            <label>
              <span>Plan</span>
              <select name="plan" value={checkoutForm.plan} onChange={handleCheckoutChange}>
                <option value="basic">Basic</option>
                <option value="pro">Pro</option>
                <option value="team">Team</option>
              </select>
            </label>

            <label>
              <span>Nombre</span>
              <input
                name="customerName"
                value={checkoutForm.customerName}
                onChange={handleCheckoutChange}
                placeholder="Tu nombre"
              />
            </label>

            <label>
              <span>Email</span>
              <input
                name="customerEmail"
                value={checkoutForm.customerEmail}
                onChange={handleCheckoutChange}
                placeholder="correo@ejemplo.com"
                type="email"
              />
            </label>

            <button className="primary-button" type="submit" disabled={sendingCheckout}>
              {sendingCheckout ? 'Procesando...' : 'Procesar pago'}
            </button>
          </form>
        </div>
      </section>
    </main>
  );
}
