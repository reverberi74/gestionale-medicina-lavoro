import { useEffect, useMemo, useState } from 'react'
import { http } from '../api/http'

export default function Home() {
  const [data, setData] = useState(null)
  const [loading, setLoading] = useState(false)
  const [err, setErr] = useState(null)

  const host = useMemo(() => window.location.host, [])
  const isTenantHost = useMemo(() => host.includes('.127.0.0.1.nip.io'), [host])

  async function load() {
    setLoading(true)
    setErr(null)
    try {
      const res = await http.get('/health')
      setData(res.data)
    } catch (e) {
      setData(null)
      setErr(e?.userMessage || 'Errore sconosciuto')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    load()
  }, [])

  return (
    <div className="min-h-screen bg-neutral-50 text-neutral-900">
      <div className="mx-auto max-w-4xl px-6 py-10">
        <div className="rounded-3xl border border-neutral-200 bg-white p-6 shadow-sm">
          <div className="flex items-start justify-between gap-4">
            <div>
              <h1 className="text-xl font-semibold">GMDL — Core FE Smoke Test</h1>
              <p className="mt-2 text-sm text-neutral-600">
                Frontend host:{' '}
                <span className="rounded-md bg-neutral-100 px-2 py-1 font-mono text-xs font-medium">
                  {host}
                </span>
              </p>
            </div>

            <div className="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-800">
              Tailwind ON <span aria-hidden>✅</span>
            </div>
          </div>

          <div className="mt-5 flex flex-wrap items-center gap-3">
            <button
              onClick={load}
              disabled={loading}
              className="rounded-xl border border-neutral-200 bg-neutral-900 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-neutral-800 disabled:cursor-not-allowed disabled:opacity-60"
            >
              {loading ? 'Carico...' : 'Ricarica /api/health'}
            </button>

            <a
              className="text-sm font-medium text-neutral-700 underline decoration-neutral-300 underline-offset-4 hover:text-neutral-900"
              href="http://acme.127.0.0.1.nip.io:5349"
            >
              Apri tenant demo (acme)
            </a>

            <span className="text-xs text-neutral-500">
              (se questo link funziona + la UI è “bella”, Tailwind + allowedHosts sono ok)
            </span>
          </div>
        </div>

        {err && (
          <div className="mt-6 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            <div className="font-semibold">Errore</div>
            <div className="mt-1">{err}</div>
            <div className="mt-2 text-xs text-red-700">
              Verifica che Laravel sia su <span className="font-mono">http://127.0.0.1:8001</span> e che Docker infra sia UP.
            </div>
          </div>
        )}

        <div className="mt-6 rounded-3xl border border-neutral-200 bg-white p-6 shadow-sm">
          <div className="flex items-center justify-between">
            <h2 className="text-sm font-semibold text-neutral-800">Risposta API</h2>
            <span className="text-xs text-neutral-500">
              {data ? 'ok' : loading ? 'loading' : '—'} {isTenantHost ? '(tenant host)' : '(root host)'}
            </span>
          </div>

          <pre className="mt-3 min-h-[180px] overflow-auto rounded-2xl border border-neutral-200 bg-neutral-50 p-4 text-xs leading-relaxed">
            {data ? JSON.stringify(data, null, 2) : loading ? 'loading…' : 'nessun dato'}
          </pre>

          <div className="mt-3 text-xs text-neutral-500">
            Prova anche: <span className="font-mono">http://127.0.0.1:8001/api/health</span>
          </div>
        </div>
      </div>
    </div>
  )
}
