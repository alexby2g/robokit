import api from '../services/api'

export const downloadPdf = async (url, filename) => {
  const response = await api.get(url, { responseType: 'blob' })
  const blob = new Blob([response.data], { type: 'application/pdf' })
  const href = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = href
  link.download = filename
  document.body.appendChild(link)
  link.click()
  link.remove()
  URL.revokeObjectURL(href)
}

export const openProtectedFile = async (httpClient, url) => {
  const popup = typeof window !== 'undefined' ? window.open('about:blank', '_blank') : null

  try {
    const response = await httpClient.get(url, { responseType: 'blob' })
    const contentType = response.headers?.['content-type'] || 'application/octet-stream'
    const blob = new Blob([response.data], { type: contentType })
    const href = URL.createObjectURL(blob)

    if (popup) {
      popup.location.href = href
    } else {
      const link = document.createElement('a')
      link.href = href
      link.target = '_blank'
      link.rel = 'noopener noreferrer'
      document.body.appendChild(link)
      link.click()
      link.remove()
    }

    window.setTimeout(() => URL.revokeObjectURL(href), 60_000)
  } catch (error) {
    if (popup) popup.close()
    throw error
  }
}

