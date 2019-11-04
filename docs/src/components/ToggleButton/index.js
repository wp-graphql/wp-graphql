import React from 'react'
import classNames from 'classnames'
import './style.css'

export default ({ onClick, label, state }) => {
  const className = classNames(
    'toggle-button',
    { green: state === true || state === 'on' || state === 'green' },
    { red: state === false || state === 'off' || state === 'red' },
    { yellow: state === 'loading' || state === 'yellow' },
    { normal: state === undefined }
  )
  return ( <button className={className} onClick={onClick}>{label}</button>)
}