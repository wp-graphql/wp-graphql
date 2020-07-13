import React from 'react'
import { Alert } from 'antd'

const Note = ({type = 'info', title = '', children}) => {
  return(
    <Alert
      type={type}
      message={title}
      description={children}
    />
  )
}

export default Note
