@extends('errors.layout')
@section('codigo', '403')
@section('titulo', 'Acceso denegado')
@section('mensaje', ($exception->getMessage() && ! str_starts_with($exception->getMessage(), 'This action')) ? $exception->getMessage() : 'No tienes permiso para acceder a esta sección.')

